<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'Clinic Admin' => [
                'clinic.view', 'clinic.update',
                'doctor.view', 'doctor.create', 'doctor.update', 'doctor.delete',
                'receptionist.view', 'receptionist.create', 'receptionist.update',
                'schedule.view', 'schedule.manage',
                'service.view', 'service.create', 'service.update', 'service.delete',
                'patient.view', 'patient.create', 'patient.update',
                'appointment.view', 'appointment.create', 'appointment.update', 'appointment.cancel',
                'queue.view', 'queue.manage', 'queue.call',
                'visit.view', 'revenue.view', 'dashboard.view', 'audit_log.view',
            ],
            'Doctor' => [
                'doctor.view', 'schedule.view', 'service.view', 'patient.view',
                'appointment.view', 'queue.view', 'queue.call',
                'visit.view', 'visit.start', 'visit.complete',
                'medical_record.view', 'medical_record.create', 'medical_record.update',
                'dashboard.view',
            ],
            'Receptionist' => [
                'doctor.view', 'schedule.view', 'service.view',
                'patient.view', 'patient.create', 'patient.update',
                'appointment.view', 'appointment.create', 'appointment.update', 'appointment.cancel',
                'queue.view', 'queue.manage', 'queue.call', 'visit.view', 'dashboard.view',
            ],
            'Patient' => [
                'service.view', 'doctor.view', 'schedule.view',
                'appointment.view', 'appointment.create', 'appointment.cancel',
                'queue.view', 'dashboard.view',
            ],
        ];

        foreach (array_unique(array_merge(...array_values($roles))) as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach ($roles as $name => $permissions) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
                ->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
