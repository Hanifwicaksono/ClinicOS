<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicClinicTest extends TestCase
{
    use RefreshDatabase;

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
}
