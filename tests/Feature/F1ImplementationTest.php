<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\SubscriptionPeriod;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Models\User;
use App\Services\PatientDuplicateService;
use App\Services\SubscriptionService;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class F1ImplementationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, SubscriptionPlanSeeder::class]);
    }

    public function test_grant_form_renders_valid_binding_and_process_feedback(): void
    {
        $clinic = Clinic::factory()->create();
        $platform = User::factory()->platformAdmin()->create();
        $content = $this->actingAs($platform)->get(route('platform.tenants.show', $clinic))->assertOk()->getContent();
        $this->assertFalse(str_contains($content, '@js('), 'Grant HTML must not contain an uncompiled directive.');
        $this->assertTrue(str_contains($content, 'Memproses'), 'Grant form must expose pending feedback.');
    }

    public function test_duplicate_candidates_after_the_first_eight_are_reachable(): void
    {
        [$clinic, $staff] = $this->receptionist();
        $patients = Patient::factory()->count(17)->for($clinic)->create(['phone' => '081299990000', 'nik' => null]);
        $payload = ['phone' => '+62 (812) 9999-0000'];
        $first = $this->actingAs($staff)->postJson(route('receptionist.patients.duplicates'), $payload)
            ->assertOk()->assertJsonCount(8, 'candidates')->assertJsonStructure(['next_cursor', 'token']);
        $second = $this->postJson(route('receptionist.patients.duplicates'), [...$payload, 'cursor' => $first->json('next_cursor')])
            ->assertOk()->assertJsonCount(8, 'candidates')->assertJsonPath('token', $first->json('token'));
        $third = $this->postJson(route('receptionist.patients.duplicates'), [...$payload, 'cursor' => $second->json('next_cursor')])
            ->assertOk()->assertJsonCount(1, 'candidates')->assertJsonPath('next_cursor', null);
        $ids = [...array_column($first->json('candidates'), 'id'), ...array_column($second->json('candidates'), 'id'), ...array_column($third->json('candidates'), 'id')];
        $this->assertSame($patients->modelKeys(), $ids);
        $this->assertDatabaseCount('patients', 17);
    }

    /** @return array<string, array{int}> */
    public static function candidateCounts(): array
    {
        return ['none' => [0], 'one' => [1], 'eight' => [8], 'nine' => [9], 'ten' => [10]];
    }

    #[DataProvider('candidateCounts')]
    public function test_page_boundaries_and_existing_review_tokens_stay_compatible(int $count): void
    {
        [$clinic, $staff] = $this->receptionist();
        Patient::factory()->count($count)->for($clinic)->create(['phone' => '081299990000', 'nik' => null]);
        $data = ['phone' => '081299990000'];
        $duplicates = app(PatientDuplicateService::class);
        $response = $this->actingAs($staff)->postJson(route('receptionist.patients.duplicates'), $data)
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(min(8, $count), 'candidates');
        $this->assertSame($duplicates->reviewToken($clinic->id, $data, $duplicates->candidates($clinic->id, $data)), $response->json('token'));
        $this->assertSame($count > 8, is_string($response->json('next_cursor')));
    }

    public function test_cursor_tampering_invalid_shapes_and_oversized_input_are_rejected(): void
    {
        [$clinic, $staff] = $this->receptionist();
        Patient::factory()->count(10)->for($clinic)->create(['phone' => '081299990000']);
        $this->actingAs($staff);
        foreach (['forged', str_repeat('x', 4097), Crypt::encryptString('not-json'), Crypt::encryptString('{}'), Crypt::encryptString('null')] as $cursor) {
            $this->postJson(route('receptionist.patients.duplicates'), ['phone' => '081299990000', 'cursor' => $cursor])
                ->assertUnprocessable()->assertJsonValidationErrors('cursor')->assertJsonMissingPath('candidates');
        }
        $this->assertDatabaseCount('patients', 10);
    }

    public function test_cursor_cannot_cross_tenant_criteria_or_expiry(): void
    {
        [$clinic, $staff] = $this->receptionist();
        Patient::factory()->count(10)->for($clinic)->create(['phone' => '081299990000']);
        $data = ['name' => 'Kandidat', 'phone' => '081299990000'];
        $cursor = $this->actingAs($staff)->postJson(route('receptionist.patients.duplicates'), $data)->assertOk()->json('next_cursor');
        $this->postJson(route('receptionist.patients.duplicates'), [...$data, 'name' => 'Kriteria berubah', 'cursor' => $cursor])
            ->assertUnprocessable()->assertJsonValidationErrors('cursor');
        [$foreign, $otherStaff] = $this->receptionist();
        Patient::factory()->for($foreign)->create(['phone' => '081299990000']);
        $this->actingAs($otherStaff)->postJson(route('receptionist.patients.duplicates'), [...$data, 'clinic_id' => $clinic->id, 'cursor' => $cursor])
            ->assertUnprocessable()->assertJsonValidationErrors('cursor')->assertJsonMissingPath('candidates');
        $this->actingAs($staff);
        $this->travel(16)->minutes();
        $this->postJson(route('receptionist.patients.duplicates'), [...$data, 'cursor' => $cursor])
            ->assertUnprocessable()->assertJsonValidationErrors('cursor');
    }

    /** @return array<string, array{string}> */
    public static function candidateChanges(): array
    {
        return ['insert' => ['insert'], 'delete' => ['delete'], 'later page edit' => ['edit'], 'inactive' => ['inactive']];
    }

    #[DataProvider('candidateChanges')]
    public function test_changes_beyond_the_first_page_invalidate_the_cursor(string $change): void
    {
        [$clinic, $staff] = $this->receptionist();
        $patients = Patient::factory()->count(10)->for($clinic)->create(['phone' => '081299990000']);
        $data = ['phone' => '081299990000'];
        $first = $this->actingAs($staff)->postJson(route('receptionist.patients.duplicates'), $data)->assertOk();
        $later = $patients->last();
        match ($change) {
            'insert' => Patient::factory()->for($clinic)->create(['phone' => '081299990000']),
            'delete' => $later->delete(),
            'edit' => DB::table('patients')->where('id', $later->id)->update(['name' => 'Berubah tanpa timestamp']),
            'inactive' => $later->update(['is_active' => false]),
        };
        $this->postJson(route('receptionist.patients.duplicates'), [...$data, 'cursor' => $first->json('next_cursor')])
            ->assertUnprocessable()->assertJsonValidationErrors('cursor')->assertJsonMissingPath('candidates');
        $this->postJson(route('receptionist.patients.duplicates'), $data)->assertOk();
    }

    public function test_paging_reuses_all_matchers_and_keeps_inactive_and_foreign_profiles_unchanged(): void
    {
        [$clinic, $staff] = $this->receptionist();
        $patients = Patient::factory()->count(10)->for($clinic)->create(['name' => 'Nama Keluarga', 'birth_date' => '1990-01-01', 'phone' => '081299990000', 'nik' => null]);
        DB::table('patients')->where('id', $patients->last()->id)->update(['phone' => '+62 (812) 9999.0000', 'is_active' => false]);
        $foreign = Patient::factory()->create(['name' => 'Foreign marker', 'phone' => '081299990000']);
        $this->actingAs($staff);
        foreach ([['phone' => '+62 812.9999-0000'], ['name' => 'NAMA KELUARGA', 'birth_date' => '1990-01-01']] as $data) {
            $first = $this->postJson(route('receptionist.patients.duplicates'), $data)->assertOk()->assertJsonCount(8, 'candidates');
            $this->postJson(route('receptionist.patients.duplicates'), [...$data, 'cursor' => $first->json('next_cursor')])
                ->assertOk()->assertJsonCount(2, 'candidates')->assertJsonPath('candidates.1.id', $patients->last()->id)->assertSee('Nonaktif')->assertDontSee('Foreign marker');
        }
        $patients->last()->update(['nik' => 'NIK-F1-unique']);
        $this->postJson(route('receptionist.patients.duplicates'), ['nik' => 'NIK-F1-unique'])->assertOk()->assertJsonCount(1, 'candidates');
        $this->assertFalse($patients->last()->fresh()->is_active);
        $this->assertNull($patients->last()->fresh()->user_id);
        $this->assertSame($foreign->name, $foreign->fresh()->name);
        $this->assertDatabaseCount('patients', 11);
    }

    public function test_review_is_still_required_and_token_from_later_page_can_only_confirm_explicit_creation(): void
    {
        [$clinic, $staff] = $this->receptionist();
        Patient::factory()->count(10)->for($clinic)->create(['phone' => '081299990000']);
        $data = ['name' => 'Orang berbeda terkonfirmasi', 'phone' => '081299990000', 'birth_date' => '1988-01-01', 'gender' => 'MALE', 'is_active' => true];
        $first = $this->actingAs($staff)->postJson(route('receptionist.patients.duplicates'), $data)->assertOk();
        $second = $this->postJson(route('receptionist.patients.duplicates'), [...$data, 'cursor' => $first->json('next_cursor')])->assertOk();
        $this->postJson(route('receptionist.patients.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('duplicate_reviewed');
        $this->postJson(route('receptionist.patients.store'), [...$data, 'duplicate_reviewed' => true, 'duplicate_review_token' => str_repeat('f', 64)])
            ->assertUnprocessable()->assertJsonValidationErrors('duplicate_reviewed');
        $this->postJson(route('receptionist.patients.store'), [...$data, 'phone' => '+62 81299990000', 'duplicate_reviewed' => true, 'duplicate_review_token' => $second->json('token')])
            ->assertUnprocessable()->assertJsonValidationErrors('duplicate_reviewed');
        $this->assertDatabaseCount('patients', 10);
        $this->post(route('receptionist.patients.store'), [...$data, 'duplicate_reviewed' => true, 'duplicate_review_token' => $second->json('token')])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('patients', 11);
        $this->assertNull(Patient::query()->where('name', $data['name'])->sole()->user_id);
    }

    /** @return array<string, array{string, bool}> */
    public static function roles(): array
    {
        return ['admin' => ['Clinic Admin', true], 'receptionist' => ['Receptionist', true], 'doctor' => ['Doctor', false], 'patient' => ['Patient', false], 'platform' => ['Platform Admin', false]];
    }

    #[DataProvider('roles')]
    public function test_candidate_endpoint_preserves_role_boundaries(string $role, bool $allowed): void
    {
        [, $user] = $this->receptionist();
        $user->syncRoles([$role]);
        $response = $this->actingAs($user)->postJson(route('receptionist.patients.duplicates'), ['phone' => '081299990000']);
        $allowed ? $response->assertOk() : $response->assertForbidden();
    }

    public function test_grant_validation_retry_preserves_idempotency_audit_and_history(): void
    {
        $clinic = Clinic::factory()->create();
        $platform = User::factory()->platformAdmin()->create();
        $price = SubscriptionPrice::query()->whereRelation('plan', 'code', 'team')->firstOrFail();
        $data = ['subscription_price_id' => $price->id, 'grant_request_id' => (string) Str::uuid(), 'reason' => '   '];
        $this->actingAs($platform)->postJson(route('platform.subscription-grants.store', $clinic), $data)
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertDatabaseCount('subscription_periods', 0);
        $data['reason'] = 'Retry eksplisit setelah validasi';
        $this->post(route('platform.subscription-grants.store', $clinic), $data)->assertRedirect()->assertSessionHasNoErrors();
        $before = SubscriptionPeriod::query()->sole()->getRawOriginal();
        $this->post(route('platform.subscription-grants.store', $clinic), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($before, SubscriptionPeriod::query()->sole()->getRawOriginal());
        $this->assertSame(1, AuditLog::query()->where('action', 'subscription.granted')->count());
        $this->postJson(route('platform.subscription-grants.store', $clinic), [...$data, 'reason' => 'Payload berbeda'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('subscription_periods', 1);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_empty_catalog_keeps_the_grant_button_disabled_on_the_server(): void
    {
        SubscriptionPlan::query()->update(['is_active' => false]);
        $this->actingAs(User::factory()->platformAdmin()->create())
            ->get(route('platform.tenants.show', Clinic::factory()->create()))->assertOk()
            ->assertSee('catalogEmpty: true', false)->assertSee('disabled="disabled"', false)->assertDontSee('@js(', false);
    }

    /** @return array{Clinic, User} */
    private function receptionist(): array
    {
        $clinic = Clinic::factory()->create();
        $clinic->settings()->create(['timezone' => 'Asia/Jakarta', 'opening_time' => '08:00', 'closing_time' => '20:00']);
        app(SubscriptionService::class)->activateFree($clinic);
        $staff = User::factory()->forClinic($clinic)->create();
        $staff->assignRole('Receptionist');

        return [$clinic, $staff];
    }
}
