<?php

namespace Tests\Feature;

use App\Jobs\RevokeRealtimeAccess;
use App\Models\Clinic;
use App\Models\Permission;
use App\Models\RealtimeRevocation;
use App\Models\Role;
use App\Models\User;
use App\Services\Realtime\AuthorizationContext;
use App\Services\Realtime\ChannelManager;
use App\Services\Realtime\DeliveryFence;
use App\Services\Realtime\SessionGrants;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Reverb\Application;
use Laravel\Reverb\Connection;
use Laravel\Reverb\Contracts\WebSocketConnection;
use LogicException;
use Mockery;
use Pusher\Pusher;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class RealtimeRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function doctor(): User
    {
        $user = User::factory()->create(['clinic_id' => Clinic::factory()->create()->id]);
        $user->assignRole('Doctor');

        return $user->fresh();
    }

    public function test_security_save_epoch_and_request_are_atomic(): void
    {
        $user = $this->doctor();
        $before = $user->realtime_auth_version;
        $user->update(['is_active' => false]);
        $this->assertSame($before + 1, $user->fresh()->realtime_auth_version);
        $this->assertDatabaseHas('realtime_revocations', ['user_id' => $user->id, 'auth_version' => $before + 1]);
        $this->assertNotNull(DB::table('jobs')->where('payload', 'like', '%RevokeRealtimeAccess%')->first());
    }

    public function test_inactive_broadcast_authorization_returns_401_instead_of_a_login_document(): void
    {
        $user = $this->doctor();
        $user->update(['is_active' => false]);
        $this->actingAs($user)->postJson('/broadcasting/auth', ['channel_name' => 'private-users.'.$user->id, 'socket_id' => '1.2'])->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_password_change_invalidates_old_version_in_same_save(): void
    {
        $user = $this->doctor();
        $before = $user->realtime_auth_version;
        $user->update(['password' => Hash::make('synthetic-new-password')]);
        $this->assertSame($before + 1, $user->fresh()->realtime_auth_version);
    }

    public function test_email_change_and_loss_of_verification_revoke_private_authority(): void
    {
        $user = $this->doctor();
        $before = $user->realtime_auth_version;
        $user->update(['email' => 'synthetic-new-address@clinicos.test', 'email_verified_at' => null]);
        $this->assertSame($before + 1, $user->fresh()->realtime_auth_version);
        $this->assertFalse(app(AuthorizationContext::class)->permits('private-users.'.$user->id, app(AuthorizationContext::class)->read($user->id)));
        $this->assertDatabaseHas('realtime_revocations', ['user_id' => $user->id, 'auth_version' => $before + 1]);
    }

    public function test_noop_and_nonsecurity_profile_save_do_not_revoke(): void
    {
        $user = $this->doctor();
        $before = $user->realtime_auth_version;
        $user->update(['name' => 'Synthetic changed name']);
        $user->assignRole('Doctor');
        $this->assertSame($before, $user->fresh()->realtime_auth_version);
    }

    public function test_stale_models_cannot_reuse_or_decrease_epoch(): void
    {
        $user = $this->doctor();
        $second = $user->fresh();
        $before = $user->realtime_auth_version;
        $user->update(['is_active' => false]);
        $second->update(['password' => Hash::make('another-synthetic-password')]);
        $this->assertSame($before + 2, $second->fresh()->realtime_auth_version);
        $second->forceFill(['realtime_auth_version' => 1]);
        $this->expectException(LogicException::class);
        $second->save();
    }

    public function test_outer_rollback_restores_user_epoch_requests_and_jobs(): void
    {
        $user = $this->doctor();
        $before = $user->realtime_auth_version;
        $requests = RealtimeRevocation::count();
        $jobs = DB::table('jobs')->count();
        try {
            DB::transaction(function () use ($user): void {
                $user->update(['is_active' => false]);
                $user->syncRoles([]);
                throw new RuntimeException('Synthetic rollback');
            });
        } catch (RuntimeException) {
        }
        $this->assertSame($before, $user->fresh()->realtime_auth_version);
        $this->assertTrue($user->fresh()->is_active);
        $this->assertTrue($user->fresh()->hasRole('Doctor'));
        $this->assertSame($requests, RealtimeRevocation::count());
        $this->assertSame($jobs, DB::table('jobs')->count());
    }

    public function test_a_model_reused_after_outer_rollback_still_writes_the_new_epoch(): void
    {
        $user = $this->doctor();
        $before = $user->realtime_auth_version;
        DB::beginTransaction();
        $user->update(['is_active' => false]);
        DB::rollBack();
        $user->update(['password' => Hash::make('synthetic-after-rollback')]);
        $this->assertSame($before + 1, $user->fresh()->realtime_auth_version);
        $this->assertDatabaseHas('realtime_revocations', ['user_id' => $user->id, 'auth_version' => $before + 1]);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_role_revoke_and_restore_do_not_restore_old_epoch(): void
    {
        $user = $this->doctor();
        $before = $user->realtime_auth_version;
        $user->removeRole('Doctor');
        $revoked = $user->fresh()->realtime_auth_version;
        $user->assignRole('Doctor');
        $this->assertGreaterThan($before, $revoked);
        $this->assertGreaterThan($revoked, $user->fresh()->realtime_auth_version);
    }

    public function test_role_permission_change_fans_out_to_members_only(): void
    {
        $first = $this->doctor();
        $second = $this->doctor();
        $control = User::factory()->create(['clinic_id' => $first->clinic_id]);
        $control->assignRole('Receptionist');
        $controlBefore = $control->fresh()->realtime_auth_version;
        Role::findByName('Doctor')->revokePermissionTo('queue.view');
        $this->assertGreaterThan($first->realtime_auth_version, $first->fresh()->realtime_auth_version);
        $this->assertGreaterThan($second->realtime_auth_version, $second->fresh()->realtime_auth_version);
        $this->assertSame($controlBefore, $control->fresh()->realtime_auth_version);
        $this->assertFalse(app(AuthorizationContext::class)->permits('private-clinic.'.$first->clinic_id.'.queues', app(AuthorizationContext::class)->read($first->id)));
    }

    public function test_direct_revoke_preserves_inherited_permission_but_bumps_epoch(): void
    {
        $user = $this->doctor();
        $before = $user->realtime_auth_version;
        $user->syncPermissions(['queue.view']);
        $assigned = $user->fresh()->realtime_auth_version;
        $user->revokePermissionTo('queue.view');
        $this->assertGreaterThan($before, $assigned);
        $this->assertGreaterThan($assigned, $user->fresh()->realtime_auth_version);
        $this->assertContains('queue.view', app(AuthorizationContext::class)->read($user->id)['permissions']);
    }

    public function test_permission_and_role_delete_invalidate_members(): void
    {
        $user = $this->doctor();
        $before = $user->realtime_auth_version;
        Permission::findByName('queue.view')->delete();
        $this->assertGreaterThan($before, $user->fresh()->realtime_auth_version);
        $before = $user->fresh()->realtime_auth_version;
        Role::findByName('Doctor')->delete();
        $this->assertGreaterThan($before, $user->fresh()->realtime_auth_version);
    }

    public function test_tenant_change_and_reactivation_each_invalidate_epoch(): void
    {
        $user = $this->doctor();
        $before = $user->realtime_auth_version;
        $user->update(['clinic_id' => Clinic::factory()->create()->id, 'is_active' => false]);
        $inactive = $user->fresh()->realtime_auth_version;
        $user->update(['is_active' => true]);
        $this->assertGreaterThan($before, $inactive);
        $this->assertGreaterThan($inactive, $user->fresh()->realtime_auth_version);

    }

    public function test_fence_cancels_deferred_old_write_without_rechecking_authority(): void
    {
        $fence = new DeliveryFence;
        $writes = 0;
        $deferred = fn () => $fence->send(7, 3, function () use (&$writes): void {
            $writes++;
        });
        $this->assertTrue($fence->advance(7, 4));
        $this->assertFalse($deferred());
        $this->assertSame(0, $writes);
        $this->assertTrue($fence->send(7, 4, function () use (&$writes): void {
            $writes++;
        }));
        $this->assertSame(1, $writes);
    }

    public function test_fence_cannot_acknowledge_an_admitted_active_send(): void
    {
        $fence = new DeliveryFence;
        $ackDuringWrite = true;
        $fence->send(7, 3, function () use ($fence, &$ackDuringWrite): void {
            $ackDuringWrite = $fence->advance(7, 4);
        });
        $this->assertFalse($ackDuringWrite);
        $this->assertTrue($fence->advance(7, 4));
        $this->assertFalse($fence->send(7, 3, fn () => $this->fail('Old write passed acknowledged fence')));
        $this->assertTrue($fence->advance(7, 2));
        $this->assertFalse($fence->send(7, 3, fn () => $this->fail('Fence decreased on stale retry')));
    }

    public function test_ack_requires_every_old_transport_close_and_not_just_disconnect_requests(): void
    {
        $application = Mockery::mock(Application::class);
        $application->shouldReceive('id')->andReturn('test-app');
        $transportA = Mockery::mock(WebSocketConnection::class);
        $transportB = Mockery::mock(WebSocketConnection::class);
        $a = new Connection($transportA, $application, null);
        $b = new Connection($transportB, $application, null);
        $fence = new DeliveryFence;
        $ack = 0;
        $fence->acknowledgeAfterClose(7, 4, [$a, $b], function () use (&$ack): void {
            $ack++;
        });
        $this->assertSame(0, $ack);
        $fence->closed($a);
        $this->assertSame(0, $ack);
        $fence->closed($b);
        $this->assertSame(1, $ack);
        $this->assertFalse($fence->send(7, 3, fn () => $this->fail('Delayed old write bypassed transport-close ACK')));
    }

    public function test_transport_http_success_cannot_manufacture_a_fence_ack(): void
    {
        $user = $this->doctor();
        $user->removeRole('Doctor');
        $request = RealtimeRevocation::where('user_id', $user->id)->latest('auth_version')->firstOrFail();
        $pusher = Mockery::mock(Pusher::class);
        $pusher->shouldReceive('trigger')->once()->andReturn((object) []);
        $broadcaster = Mockery::mock(PusherBroadcaster::class);
        $broadcaster->shouldReceive('getPusher')->andReturn($pusher);
        Broadcast::shouldReceive('connection')->with('reverb')->andReturn($broadcaster);
        try {
            (new RevokeRealtimeAccess($request->id))->handle();
            $this->fail('HTTP success without server ACK must remain failed/pending.');
        } catch (RuntimeException $error) {
            $this->assertStringNotContainsString('auth_signature', $error->getMessage());
        }
        $this->assertNull($request->fresh()->acknowledged_at);
        $this->assertNotNull($request->fresh()->failed_at);
        $this->assertFalse($user->fresh()->hasRole('Doctor'));
    }

    public function test_acknowledged_retry_is_idempotent_and_does_not_contact_transport(): void
    {
        $user = $this->doctor();
        $user->removeRole('Doctor');
        $request = RealtimeRevocation::where('user_id', $user->id)->firstOrFail();
        $request->update(['acknowledged_at' => now(), 'ack_node' => (string) Str::uuid()]);
        Broadcast::shouldReceive('connection')->never();
        (new RevokeRealtimeAccess($request->id))->handle();
        $this->assertNotNull($request->fresh()->acknowledged_at);
    }

    public function test_signed_identity_binds_user_epoch_tenant_application_and_channel(): void
    {
        $user = $this->doctor();
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'dummy-key',
            'broadcasting.connections.reverb.secret' => 'dummy-secret', 'broadcasting.connections.reverb.app_id' => 'dummy-app']);
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');
        $channel = 'private-clinic.'.$user->clinic_id.'.queues';
        $response = $this->actingAs($user)->postJson('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1.2'])->assertOk();
        $identity = json_decode($response->json('channel_data'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame((string) $user->id, $identity['user_id']);
        $this->assertSame((string) $user->realtime_auth_version, $identity['version']);
        $this->assertSame((string) $user->clinic_id, $identity['clinic_id']);
        $this->assertSame('dummy-app', $identity['app_id']);
        $this->assertSame($channel, $identity['channel']);
        $context = app(AuthorizationContext::class);
        $this->assertTrue($context->allows($channel, $identity, 'dummy-app'));
        $this->assertFalse($context->allows($channel, $identity, 'foreign-app'));
        $this->assertFalse($context->allows('private-users.'.$user->id, $identity, 'dummy-app'));
        $user->removeRole('Doctor');
        $this->assertFalse($context->allows($channel, $identity, 'dummy-app'));
        $this->postJson('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1.2'])->assertForbidden();
    }

    public function test_different_queue_database_or_sync_driver_rolls_back_security_change(): void
    {
        $user = $this->doctor();
        $before = $user->realtime_auth_version;
        config(['queue.connections.database.driver' => 'sync']);
        try {
            $user->update(['is_active' => false]);
            $this->fail('Nontransactional queue must reject security mutation.');
        } catch (LogicException) {
        }
        $this->assertTrue($user->fresh()->is_active);
        $this->assertSame($before, $user->fresh()->realtime_auth_version);
        $this->assertSame(0, RealtimeRevocation::where('user_id', $user->id)->count());
    }

    public function test_configured_role_side_assignment_and_permission_side_sync_are_versioned(): void
    {
        $user = $this->doctor();
        $before = $user->realtime_auth_version;
        $role = Role::findByName('Receptionist');
        $role->assignToModels([$user]);
        $afterAssignment = $user->fresh()->realtime_auth_version;
        $role->removeFromModels([$user]);
        $this->assertGreaterThan($before, $afterAssignment);
        $this->assertGreaterThan($afterAssignment, $user->fresh()->realtime_auth_version);
        $before = $user->fresh()->realtime_auth_version;
        Permission::findByName('queue.view')->syncRoles(['Receptionist']);
        $this->assertGreaterThan($before, $user->fresh()->realtime_auth_version);
    }

    public function test_first_role_grant_establishes_a_new_epoch_without_fabricating_a_transport_ack(): void
    {
        $user = User::factory()->create(['clinic_id' => Clinic::factory()->create()->id]);
        $this->assertFalse(app(AuthorizationContext::class)->permits('private-users.'.$user->id, app(AuthorizationContext::class)->read($user->id)));
        $user->assignRole('Doctor');
        $this->assertSame(2, $user->fresh()->realtime_auth_version);
        $this->assertSame(0, RealtimeRevocation::where('user_id', $user->id)->count());
    }

    public function test_reverb_contract_and_concrete_resolve_to_the_same_guarded_manager(): void
    {
        $this->assertSame(app(\Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager::class), app(ChannelManager::class));
        $this->assertInstanceOf(ChannelManager::class, app(\Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager::class));
    }

    public function test_session_scope_is_private_fail_closed_and_independent_of_other_sessions(): void
    {
        $user = $this->doctor();
        $grants = app(SessionGrants::class);
        $a = $grants->issue('synthetic-session-a', $user->id);
        $b = $grants->issue('synthetic-session-b', $user->id);
        $this->assertStringNotContainsString('synthetic-session-a', $a);
        $this->assertTrue($grants->allows($a, $user->id));
        $this->assertFalse($grants->allows($a, $user->id + 1));
        $grants->revoke('synthetic-session-a');
        $this->assertFalse($grants->allows($a, $user->id));
        $this->assertTrue($grants->allows($b, $user->id));
        DB::table('realtime_session_grants')->where('scope', $b)->update(['expires_at' => time() - 1]);
        $grants->touch('synthetic-session-b', $user->id);
        $this->assertFalse($grants->allows($b, $user->id));
    }

    public function test_delayed_authorization_cannot_resurrect_a_logged_out_session(): void
    {
        $user = $this->doctor();
        $grants = app(SessionGrants::class);
        $scope = $grants->issue('synthetic-delayed-auth', $user->id);
        $grants->revoke('synthetic-delayed-auth');
        $this->assertFalse($grants->allows($scope, $user->id));
        $this->expectException(AccessDeniedHttpException::class);
        $grants->issue('synthetic-delayed-auth', $user->id);
    }

    public function test_logout_before_first_scope_issue_also_prevents_delayed_initial_authorization(): void
    {
        $user = $this->doctor();
        $grants = app(SessionGrants::class);
        $grants->revoke('synthetic-initial-auth-race');
        $this->expectException(AccessDeniedHttpException::class);
        $grants->issue('synthetic-initial-auth-race', $user->id);
    }

    public function test_retry_is_tenant_scoped_and_does_not_require_operational_subscription_writes(): void
    {
        $staff = $this->doctor();
        $staff->removeRole('Doctor');
        $revocation = RealtimeRevocation::where('user_id', $staff->id)->firstOrFail();
        $admin = User::factory()->create(['clinic_id' => $staff->clinic_id]);
        $admin->assignRole('Clinic Admin');
        $foreign = User::factory()->create(['clinic_id' => Clinic::factory()->create()->id]);
        $foreign->assignRole('Clinic Admin');
        $this->actingAs($foreign)->post(route('admin.realtime-revocations.retry', $revocation))->assertForbidden();
        $version = $staff->fresh()->realtime_auth_version;
        $this->actingAs($admin)->post(route('admin.realtime-revocations.retry', $revocation))->assertRedirect();
        $this->assertSame($version, $staff->fresh()->realtime_auth_version);
        $this->assertFalse($staff->fresh()->hasRole('Doctor'));
        $this->assertNull($revocation->fresh()->acknowledged_at);
    }
}
