<?php

namespace Tests\Feature\Admin;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Service;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_clinic_admin_can_create_doctor_and_assign_services(): void
    {
        Notification::fake();
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $service = Service::factory()->for($clinic)->create();

        $this->actingAs($admin)
            ->get(route('admin.staff.create'))
            ->assertOk()
            ->assertSee('name="password"', false);

        $response = $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name' => 'dr. Nadia Putri',
            'email' => 'nadia@clinic.test',
            'phone' => '08123456789',
            'password' => 'StaffPass123!',
            'role' => 'Doctor',
            'specialization' => 'Dokter Umum',
            'license_number' => 'SIP-12345',
            'bio' => 'Berpengalaman menangani pasien keluarga.',
            'service_ids' => [$service->id],
        ]);

        $doctorUser = User::query()->where('email', 'nadia@clinic.test')->firstOrFail();

        $response->assertRedirect(route('admin.staff.index'));
        $this->assertTrue($doctorUser->hasRole('Doctor'));
        $this->assertTrue(Hash::check('StaffPass123!', $doctorUser->password));
        $this->assertDatabaseHas('doctors', [
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'specialization' => 'Dokter Umum',
        ]);
        $this->assertDatabaseHas('doctor_service', [
            'doctor_id' => $doctorUser->doctor->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_clinic_admin_can_change_staff_password(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $staff = User::factory()->forClinic($clinic)->create();
        $staff->assignRole('Receptionist');

        $this->actingAs($admin)
            ->put(route('admin.staff.update', $staff), [
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'password' => 'NewStaffPass123!',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.staff.index'));

        $this->assertTrue(Hash::check('NewStaffPass123!', $staff->refresh()->password));
    }

    public function test_clinic_admin_cannot_edit_staff_from_another_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $otherStaff = User::factory()->forClinic(Clinic::factory()->create())->create();
        $otherStaff->assignRole('Receptionist');

        $this->actingAs($admin)
            ->get(route('admin.staff.edit', $otherStaff))
            ->assertForbidden();
    }

    /** @return array<string, array{string, string}> */
    public static function staffRoles(): array
    {
        return ['doctor' => ['Doctor', 'doctor.dashboard'], 'receptionist' => ['Receptionist', 'receptionist.dashboard']];
    }

    public function test_failed_staff_update_preserves_password_session_and_verified_email(): void
    {
        Notification::fake();
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $staff = User::factory()->forClinic($clinic)->create();
        $staff->assignRole('Receptionist');
        $oldSessionHash = Auth::guard('web')->hashPasswordForCookie($staff->getAuthPassword());
        $this->mock(AuditLogger::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Synthetic rollback'));

        $this->actingAs($admin)->put(route('admin.staff.update', $staff), [
            'name' => $staff->name, 'email' => 'rollback@clinic.test', 'is_active' => true, 'password' => 'RollbackStaff123!',
        ])->assertStatus(500);

        $this->assertSame($staff->email, $staff->fresh()->email);
        $this->assertNotNull($staff->fresh()->email_verified_at);
        $this->assertTrue(Hash::check('password', $staff->fresh()->password));
        Notification::assertNothingSent();
        $this->actingAs($staff->fresh())->withSession(['password_hash_web' => $oldSessionHash])
            ->get(route('receptionist.dashboard'))->assertOk();
    }

    public function test_verification_transport_failure_is_reported_without_reversing_committed_email_change(): void
    {
        Exceptions::fake();
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $staff = User::factory()->forClinic($clinic)->create();
        $staff->assignRole('Receptionist');
        $this->mock(Dispatcher::class)->shouldReceive('send')->once()->andThrow(new RuntimeException('Synthetic mail failure'));

        $this->actingAs($admin)->put(route('admin.staff.update', $staff), [
            'name' => $staff->name, 'email' => 'retry-verification@clinic.test', 'is_active' => true,
        ])->assertRedirect()->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'meminta ulang tautan'));

        $this->assertSame('retry-verification@clinic.test', $staff->fresh()->email);
        $this->assertNull($staff->fresh()->email_verified_at);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_staff_creation_mail_failure_returns_a_recoverable_created_account(): void
    {
        Exceptions::fake();
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $this->mock(Dispatcher::class)->shouldReceive('send')->once()->andThrow(new RuntimeException('Synthetic creation mail failure'));

        $this->actingAs($admin)->postJson(route('admin.staff.store'), [
            'name' => 'Synthetic recoverable staff', 'email' => 'recoverable@clinic.test',
            'role' => 'Receptionist', 'password' => 'RecoverableStaff123!',
        ])->assertRedirect(route('admin.staff.index'))
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'meminta ulang tautan'));

        $staff = User::query()->where('email', 'recoverable@clinic.test')->sole();
        $this->assertTrue($staff->hasRole('Receptionist'));
        $this->assertNull($staff->email_verified_at);
        $this->assertTrue(Hash::check('RecoverableStaff123!', $staff->password));
        Exceptions::assertReported(RuntimeException::class);
    }

    /** @return array<string, array{string, string, bool}> */
    public static function createPermissions(): array
    {
        return [
            'doctor permission, doctor' => ['doctor.create', 'Doctor', true],
            'doctor permission, receptionist' => ['doctor.create', 'Receptionist', false],
            'receptionist permission, doctor' => ['receptionist.create', 'Doctor', false],
            'receptionist permission, receptionist' => ['receptionist.create', 'Receptionist', true],
        ];
    }

    #[DataProvider('createPermissions')]
    public function test_create_permission_matches_the_target_staff_role(string $permission, string $role, bool $allowed): void
    {
        $admin = User::factory()->forClinic(Clinic::factory()->create())->create();
        $admin->assignRole('Clinic Admin');
        Role::findByName('Clinic Admin')->syncPermissions([$permission]);

        $this->assertSame($allowed, $admin->fresh()->can('create', [User::class, $role]));
        $admin->update(['clinic_id' => null]);
        $this->assertFalse($admin->fresh()->can('create', [User::class, $role]));
    }

    #[DataProvider('staffRoles')]
    public function test_admin_password_change_revokes_old_staff_sessions_and_allows_new_login(string $role, string $dashboard): void
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $staff = User::factory()->forClinic($clinic)->create();
        $staff->assignRole($role);
        if ($role === 'Doctor') {
            Doctor::factory()->for($clinic)->for($staff, 'user')->create();
        }
        Auth::login($staff);
        $oldSessionHash = session('password_hash_web');
        Auth::login($admin);

        $this->put(route('admin.staff.update', $staff), [
            'name' => $staff->name, 'email' => $staff->email, 'specialization' => 'Synthetic QA',
            'is_active' => true, 'password' => 'ReplacementStaff123!',
        ])->assertRedirect();

        $this->actingAs($staff->fresh())->withSession(['password_hash_web' => $oldSessionHash])
            ->get(route($dashboard))->assertRedirect(route('admin.login'));
        $this->assertGuest();
        Volt::test('pages.auth.login', ['adminLogin' => true])->set('form.email', $staff->email)
            ->set('form.password', 'password')->call('login')->assertHasErrors('form.email');
        Volt::test('pages.auth.login', ['adminLogin' => true])->set('form.email', $staff->email)
            ->set('form.password', 'ReplacementStaff123!')->call('login')->assertHasNoErrors();
        $this->get(route($dashboard))->assertOk();
    }

    #[DataProvider('staffRoles')]
    public function test_changed_staff_email_gets_a_new_verification_link_and_rejects_the_old_link(string $role, string $dashboard): void
    {
        Notification::fake();
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $staff = User::factory()->forClinic($clinic)->create();
        $staff->assignRole($role);
        if ($role === 'Doctor') {
            Doctor::factory()->for($clinic)->for($staff, 'user')->create();
        }
        $oldLink = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $staff->id, 'hash' => sha1($staff->email)]);

        $this->actingAs($admin)->put(route('admin.staff.update', $staff), [
            'name' => $staff->name, 'email' => 'changed-staff@clinic.test', 'specialization' => 'Synthetic QA', 'is_active' => true,
        ])->assertRedirect();

        $staff->refresh();
        $this->assertNull($staff->email_verified_at);
        Notification::assertSentTo($staff, VerifyEmail::class);
        $this->actingAs($staff)->get(route($dashboard))->assertRedirect(route('verification.notice'));
        $this->get($oldLink)->assertForbidden();
        Notification::assertSentTo($staff, VerifyEmail::class, function (VerifyEmail $notification) use ($staff): bool {
            $this->get($notification->toMail($staff)->actionUrl)->assertRedirect();

            return true;
        });
        $this->actingAs($staff->fresh())->get(route($dashboard))->assertOk();
    }

    #[DataProvider('staffRoles')]
    public function test_staff_creation_verification_password_reset_permissions_and_deactivation(string $role, string $dashboard): void
    {
        Notification::fake();
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $service = Service::factory()->for($clinic)->create();
        $this->actingAs($admin)->postJson(route('admin.staff.store'), [
            'name' => 'Synthetic staff lifecycle', 'email' => 'lifecycle@clinic.test',
            'password' => 'InitialStaff123!', 'role' => $role,
            'specialization' => 'Synthetic QA', 'service_ids' => [$service->id],
        ])->assertRedirect(route('admin.staff.index'));
        $staff = User::query()->where('email', 'lifecycle@clinic.test')->firstOrFail();
        $this->assertTrue($staff->is_active);
        $this->assertNull($staff->email_verified_at);
        $this->assertTrue($staff->hasRole($role));
        $verificationUrl = '';
        Notification::assertSentTo($staff, VerifyEmail::class, function (VerifyEmail $notification) use ($staff, &$verificationUrl): bool {
            $verificationUrl = $notification->toMail($staff)->actionUrl;

            return true;
        });
        auth()->logout();
        Volt::test('pages.auth.login')->set('form.email', $staff->email)->set('form.password', 'InitialStaff123!')
            ->call('login')->assertHasErrors('form.email');
        Volt::test('pages.auth.login', ['adminLogin' => true])->set('form.email', $staff->email)
            ->set('form.password', 'InitialStaff123!')->call('login')->assertHasNoErrors();
        $this->get(route($dashboard))->assertRedirect(route('verification.notice'));
        $this->get($verificationUrl.'&signature=invalid')->assertForbidden();
        $this->assertNull($staff->fresh()->email_verified_at);
        $this->get($verificationUrl)->assertRedirect();
        $this->assertNotNull($staff->fresh()->email_verified_at);
        $this->actingAs($staff->fresh())->get(route($dashboard))->assertOk();

        $staffRole = Role::findByName($role);
        $staffRole->revokePermissionTo('dashboard.view');
        $this->actingAs($staff->fresh())->get(route($dashboard))->assertForbidden();
        $staffRole->givePermissionTo('dashboard.view');
        $this->actingAs($staff->fresh())->get(route($dashboard))->assertOk();
        $this->get(route('admin.staff.create'))->assertForbidden();
        $staff->syncRoles([]);
        $this->actingAs($staff->fresh())->get(route($dashboard))->assertForbidden();
        $staff->assignRole($role);

        auth()->logout();
        Volt::test('pages.auth.forgot-password')->set('email', $staff->email)->call('sendPasswordResetLink')->assertHasNoErrors();
        Notification::assertSentTo($staff, ResetPassword::class, function (ResetPassword $notification) use ($staff): bool {
            Volt::test('pages.auth.reset-password', ['token' => $notification->token])
                ->set('email', $staff->email)->set('password', 'ResetStaff123!')->set('password_confirmation', 'ResetStaff123!')
                ->call('resetPassword')->assertHasNoErrors()->assertRedirect(route('admin.login', absolute: false));

            return true;
        });
        Volt::test('pages.auth.login', ['adminLogin' => true])->set('form.email', $staff->email)
            ->set('form.password', 'InitialStaff123!')->call('login')->assertHasErrors('form.email');
        Volt::test('pages.auth.login', ['adminLogin' => true])->set('form.email', $staff->email)
            ->set('form.password', 'ResetStaff123!')->call('login')->assertHasNoErrors();

        $payload = ['name' => $staff->name, 'email' => $staff->email,
            'specialization' => 'Synthetic QA', 'service_ids' => [$service->id], 'is_active' => false,
        ];
        $this->actingAs($admin)->put(route('admin.staff.update', $staff), [...$payload,
            'role' => 'Platform Admin', 'clinic_id' => 999999, 'permissions' => ['platform.tenant.manage'],
        ])->assertRedirect();
        $this->assertTrue($staff->fresh()->hasExactRoles([$role]));
        $this->assertSame($clinic->id, $staff->fresh()->clinic_id);
        $this->actingAs($staff->fresh())->get(route($dashboard))->assertRedirect(route('admin.login'));
        $this->assertGuest();
        Volt::test('pages.auth.login', ['adminLogin' => true])->set('form.email', $staff->email)
            ->set('form.password', 'ResetStaff123!')->call('login')->assertHasErrors('form.email');
        if ($role === 'Doctor') {
            $this->assertFalse($staff->doctor->refresh()->is_active);
            $this->assertSame([$service->id], $staff->doctor->services->modelKeys());
        }
        $this->actingAs($admin)->put(route('admin.staff.update', $staff), [...$payload, 'is_active' => true])->assertRedirect();
        $this->assertTrue($staff->refresh()->is_active);
        $this->actingAs($staff)->get(route($dashboard))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.created', 'entity_id' => $staff->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.updated', 'entity_id' => $staff->id]);
    }
}
