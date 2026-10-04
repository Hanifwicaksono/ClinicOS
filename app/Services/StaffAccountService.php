<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class StaffAccountService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): User
    {
        $user = DB::transaction(function () use ($actor, $data): User {
            $user = User::query()->create([
                ...Arr::only($data, ['name', 'email', 'phone']),
                'clinic_id' => $actor->clinic_id,
                'password' => Hash::make(Str::password(32)),
                'is_active' => true,
            ]);

            $user->assignRole($data['role']);

            if ($data['role'] === 'Doctor') {
                $doctor = $user->doctor()->create(Arr::only($data, [
                    'specialization',
                    'license_number',
                    'bio',
                ]) + [
                    'clinic_id' => $actor->clinic_id,
                    'is_active' => true,
                ]);

                $doctor->services()->sync($data['service_ids'] ?? []);
            }

            $this->auditLogger->record($actor, 'staff.created', $user, [
                'role' => $data['role'],
            ]);

            return $user;
        });

        event(new Registered($user));
        Password::sendResetLink(['email' => $user->email]);

        return $user->load('doctor.services', 'roles');
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, User $staff, array $data): User
    {
        return DB::transaction(function () use ($actor, $staff, $data): User {
            $emailChanged = array_key_exists('email', $data) && $data['email'] !== $staff->email;

            $staff->fill(Arr::only($data, ['name', 'email', 'phone', 'is_active']));

            if ($emailChanged) {
                $staff->email_verified_at = null;
            }

            $staff->save();

            if ($staff->hasRole('Doctor')) {
                $doctor = $staff->doctor()->updateOrCreate(
                    ['user_id' => $staff->id],
                    Arr::only($data, ['specialization', 'license_number', 'bio']) + [
                        'clinic_id' => $actor->clinic_id,
                        'is_active' => $staff->is_active,
                    ],
                );

                $doctor->services()->sync($data['service_ids'] ?? []);
            }

            $this->auditLogger->record($actor, 'staff.updated', $staff, [
                'role' => $staff->roles()->value('name'),
                'is_active' => $staff->is_active,
            ]);

            return $staff->load('doctor.services', 'roles');
        });
    }
}
