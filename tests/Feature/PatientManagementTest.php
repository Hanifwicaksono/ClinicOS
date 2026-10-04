<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_receptionist_can_register_and_search_patient_in_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $receptionist = User::factory()->forClinic($clinic)->create();
        $receptionist->assignRole('Receptionist');

        $response = $this->actingAs($receptionist)->post(route('receptionist.patients.store'), [
            'name' => 'Siti Rahma',
            'nik' => '3273010101900001',
            'birth_date' => '1990-01-01',
            'gender' => 'FEMALE',
            'phone' => '081234567890',
            'email' => 'siti@example.test',
            'address' => 'Bandung',
            'is_active' => true,
        ]);

        $patient = Patient::query()->where('name', 'Siti Rahma')->firstOrFail();
        $response->assertRedirect(route('receptionist.patients.show', $patient));
        $this->assertStringStartsWith('RM-', $patient->medical_record_number);
        $this->actingAs($receptionist)->get(route('receptionist.patients.index', ['search' => $patient->medical_record_number]))
            ->assertOk()
            ->assertSee('Siti Rahma');
        $this->assertDatabaseHas('audit_logs', ['clinic_id' => $clinic->id, 'action' => 'patient.created']);
    }

    public function test_receptionist_cannot_view_patient_from_another_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $receptionist = User::factory()->forClinic($clinic)->create();
        $receptionist->assignRole('Receptionist');
        $otherPatient = Patient::factory()->for(Clinic::factory()->create())->create();

        $this->actingAs($receptionist)
            ->get(route('receptionist.patients.show', $otherPatient))
            ->assertForbidden();
    }
}
