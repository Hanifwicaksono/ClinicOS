<?php

namespace App\Models;

use App\UserAccountType;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['clinic_id', 'account_type', 'name', 'email', 'phone', 'is_active', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $attributes = [
        'is_active' => true,
    ];

    public function dashboardRouteName(): string
    {
        if ($this->account_type === UserAccountType::ClinicOwner) {
            if ($this->clinic_id === null) {
                return 'onboarding.clinic.create';
            }

            if (! $this->subscriptionExists()) {
                return 'onboarding.plans.index';
            }
        }

        foreach ([
            'Platform Admin' => 'platform.dashboard',
            'Clinic Admin' => 'admin.dashboard',
            'Doctor' => 'doctor.dashboard',
            'Receptionist' => 'receptionist.dashboard',
            'Patient' => 'patient.dashboard',
        ] as $role => $route) {
            if ($this->hasRole($role)) {
                return $route;
            }
        }

        return 'dashboard';
    }

    public function isClinicOwner(): bool
    {
        return $this->account_type === UserAccountType::ClinicOwner;
    }

    public function subscriptionExists(): bool
    {
        return $this->clinic_id !== null && $this->clinic()->whereHas('subscription')->exists();
    }

    public function receivesBroadcastNotificationsOn(): string
    {
        return 'users.'.$this->id;
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function ownedClinic(): HasOne
    {
        return $this->hasOne(Clinic::class, 'owner_user_id');
    }

    public function grantedSubscriptionPeriods(): HasMany
    {
        return $this->hasMany(SubscriptionPeriod::class, 'granted_by_user_id');
    }

    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    public function patientProfiles(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'account_type' => UserAccountType::class,
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
