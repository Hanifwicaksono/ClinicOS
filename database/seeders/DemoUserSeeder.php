<?php

namespace Database\Seeders;

use App\Models\User;
use App\UserAccountType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        foreach ([
            'platform' => ['Platform Administrator', 'Platform Admin', null],
            'admin' => ['Clinic Administrator', 'Clinic Admin', UserAccountType::ClinicOwner],
            'doctor' => ['dr. Budi Santoso', 'Doctor', null],
            'receptionist' => ['Siti Rahma', 'Receptionist', null],
            'patient' => ['Ahmad Fauzi', 'Patient', UserAccountType::Patient],
        ] as $prefix => [$name, $role, $accountType]) {
            $user = User::updateOrCreate(
                ['email' => $prefix.'@clinicos.test'],
                [
                    'name' => $name,
                    'account_type' => $accountType,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ],
            );

            $user->syncRoles([$role]);
        }
    }
}
