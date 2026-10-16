<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffAccountService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private SubscriptionService $subscriptionService,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): User
    {
        $user = DB::transaction(function () use ($actor, $data): User {
            $clinic = Clinic::query()->lockForUpdate()->findOrFail($actor->clinic_id);

            try {
                $this->subscriptionService->assertSeatAvailable($clinic, $data['role']);
            } catch (DomainException $exception) {
                throw ValidationException::withMessages(['role' => $exception->getMessage()]);
            }

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
            $clinic = Clinic::query()->lockForUpdate()->findOrFail($actor->clinic_id);
            $staff = User::query()->lockForUpdate()->findOrFail($staff->id);
            $deactivationOnly = false;
            $blockReason = $this->subscriptionService->operationalBlockReason($clinic);

            if ($blockReason !== null) {
                if (! $actor->isClinicOwner() || ! $staff->is_active || (bool) $data['is_active']) {
                    throw ValidationException::withMessages([
                        'is_active' => 'Akses operasional klinik sedang hanya-baca.',
                    ]);
                }

                $data = ['is_active' => false];
                $deactivationOnly = true;
            } elseif (! $staff->is_active && (bool) $data['is_active']) {
                try {
                    $this->subscriptionService->assertSeatAvailable(
                        $clinic,
                        (string) $staff->roles()->value('name'),
                    );
                } catch (DomainException $exception) {
                    throw ValidationException::withMessages(['is_active' => $exception->getMessage()]);
                }
            }

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

                if (! $deactivationOnly) {
                    $doctor->services()->sync($data['service_ids'] ?? []);
                }
            }

            $this->auditLogger->record($actor, 'staff.updated', $staff, [
                'role' => $staff->roles()->value('name'),
                'is_active' => $staff->is_active,
            ]);

            return $staff->load('doctor.services', 'roles');
        });
    }
}
