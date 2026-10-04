<?php

namespace Database\Seeders;

use App\Models\User;
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
            'admin' => ['Clinic Administrator', 'Clinic Admin'],
            'doctor' => ['dr. Budi Santoso', 'Doctor'],
            'receptionist' => ['Siti Rahma', 'Receptionist'],
            'patient' => ['Ahmad Fauzi', 'Patient'],
        ] as $prefix => [$name, $role]) {
            $user = User::updateOrCreate(
                ['email' => $prefix.'@clinicos.test'],
                [
                    'name' => $name,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ],
            );

            $user->syncRoles([$role]);
        }
    }
}
