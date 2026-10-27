<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Service;
use App\Models\User;
use App\UserAccountType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicClinicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_active_clinic_public_page_lists_only_active_services_and_doctors(): void
    {
        $clinic = Clinic::factory()->create();
        Service::factory()->for($clinic)->create(['name' => 'Konsultasi Umum', 'is_active' => true]);
        Service::factory()->for($clinic)->create(['name' => 'Layanan Rahasia', 'is_active' => false]);
        $doctorUser = User::factory()->forClinic($clinic)->create(['name' => 'dr. Aktif']);
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => 1]);

        $this->get(route('public.clinic.show', $clinic))
            ->assertOk()
            ->assertSee('Konsultasi Umum')
            ->assertSee('dr. Aktif')
            ->assertDontSee('Layanan Rahasia');
    }

    public function test_inactive_clinic_public_page_is_not_accessible(): void
    {
        $clinic = Clinic::factory()->create(['is_active' => false]);

        $this->get(route('public.clinic.show', $clinic))->assertNotFound();
    }

    public function test_public_page_displays_the_clinic_logo_when_available(): void
    {
        $clinic = Clinic::factory()->create(['logo_path' => 'clinic-logos/clinic-logo.png']);

        $this->get(route('public.clinic.show', $clinic))
            ->assertOk()
            ->assertSee('storage/clinic-logos/clinic-logo.png');
    }

    public function test_clinic_page_links_guests_to_login_and_patients_to_their_dashboard(): void
    {
        $clinic = Clinic::factory()->create();

        $this->get(route('public.clinic.show', $clinic))
            ->assertOk()
            ->assertSee('Masuk untuk booking');

        $patient = User::factory()->create(['account_type' => UserAccountType::Patient]);
        $patient->assignRole('Patient');

        $this->actingAs($patient)->get(route('public.clinic.show', $clinic))
            ->assertOk()
            ->assertSee('Buat booking')
            ->assertSee('Dashboard pasien');
    }
}
