<?php

namespace Tests\Feature;

use App\Models\MedicalRecord;
use App\Models\PatientAllergy;
use App\Models\User;
use App\PaymentStatus;
use App\QueueStatus;
use App\SubscriptionStatus;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_builds_a_complete_idempotent_and_tenant_consistent_workspace(): void
    {
        $this->runDemoSeeder();

        foreach (QueueStatus::cases() as $status) {
            $this->assertDatabaseHas('queues', ['status' => $status->value]);
        }

        foreach (PaymentStatus::cases() as $status) {
            $this->assertDatabaseHas('payments', ['status' => $status->value]);
        }

        foreach (SubscriptionStatus::cases() as $status) {
            $this->assertDatabaseHas('subscriptions', ['status' => $status->value]);
        }

        $dentist = User::query()->where('email', 'dentist@clinicos.test')->firstOrFail();
        $this->assertTrue($dentist->hasRole('Doctor'));
        $this->assertTrue(Hash::check('password', $dentist->password));
        $this->assertNotNull(MedicalRecord::query()->whereNotNull('odontogram')->first());
        $this->assertTrue(PatientAllergy::query()->where('severity', 'CRITICAL')->exists());

        foreach ($this->requiredTables() as $table) {
            $this->assertGreaterThan(0, DB::table($table)->count(), "Tabel {$table} harus memiliki fixture demo.");
        }

        $this->assertSame(0, DB::table('appointments as appointments')
            ->join('patients', 'patients.id', '=', 'appointments.patient_id')
            ->whereColumn('appointments.clinic_id', '<>', 'patients.clinic_id')
            ->count());
        $this->assertSame(0, DB::table('appointments as appointments')
            ->join('doctors', 'doctors.id', '=', 'appointments.doctor_id')
            ->whereColumn('appointments.clinic_id', '<>', 'doctors.clinic_id')
            ->count());
        $this->assertSame(0, DB::table('visits as visits')
            ->join('patients', 'patients.id', '=', 'visits.patient_id')
            ->whereColumn('visits.clinic_id', '<>', 'patients.clinic_id')
            ->count());
        $this->assertSame(0, DB::table('visits as visits')
            ->join('doctors', 'doctors.id', '=', 'visits.doctor_id')
            ->whereColumn('visits.clinic_id', '<>', 'doctors.clinic_id')
            ->count());

        $counts = collect($this->requiredTables())
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

        $this->runDemoSeeder();

        $counts->each(function (int $count, string $table): void {
            $this->assertSame($count, DB::table($table)->count(), "Seeder tidak idempotent untuk tabel {$table}.");
        });
    }

    private function runDemoSeeder(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->app->detectEnvironment(fn (): string => 'local');

        try {
            $this->seed(DemoSeeder::class);
        } finally {
            $this->app->detectEnvironment(fn (): string => $originalEnvironment);
        }
    }

    /** @return list<string> */
    private function requiredTables(): array
    {
        return [
            'users',
            'clinics',
            'clinic_settings',
            'doctors',
            'doctor_schedules',
            'services',
            'patients',
            'appointments',
            'queues',
            'visits',
            'medical_records',
            'vital_signs',
            'diagnoses',
            'treatments',
            'prescriptions',
            'prescription_items',
            'patient_allergies',
            'medical_record_revisions',
            'subscriptions',
            'subscription_periods',
            'payments',
            'payment_events',
            'subscription_reminders',
            'notifications',
            'audit_logs',
        ];
    }
}
