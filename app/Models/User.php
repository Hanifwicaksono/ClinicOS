<?php

namespace App\Models;

use App\Services\Realtime\AccessMutations;
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
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['clinic_id', 'account_type', 'name', 'email', 'google_id', 'phone', 'is_active', 'password', 'email_verified_at', 'terms_accepted_at'])]
#[Hidden(['password', 'remember_token', 'realtime_auth_version'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use HasRoles {
        assignRole as private assignRoleWithoutRevocation;
        removeRole as private removeRoleWithoutRevocation;
        syncRoles as private syncRolesWithoutRevocation;
        givePermissionTo as private givePermissionWithoutRevocation;
        revokePermissionTo as private revokePermissionWithoutRevocation;
        syncPermissions as private syncPermissionsWithoutRevocation;
    }

    protected $attributes = [
        'is_active' => true,
        'realtime_auth_version' => 1,
    ];

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            if ($this->exists && $this->isDirty('realtime_auth_version')) {
                throw new LogicException('Authorization version is managed by the access mutation boundary.');
            }
            $securityChanged = $this->exists && $this->isDirty(['password', 'is_active', 'clinic_id', 'account_type', 'email', 'email_verified_at']);
            if ($securityChanged) {
                $current = DB::table('users')->where('id', $this->id)->lockForUpdate()->firstOrFail();
                $this->setAttribute('realtime_auth_version', (int) $current->realtime_auth_version);
                $this->syncOriginalAttribute('realtime_auth_version');
                $this->setAttribute('realtime_auth_version', (int) $current->realtime_auth_version + 1);
            }
            $saved = parent::save($options);
            if ($saved && $securityChanged) {
                app(AccessMutations::class)->record($this->id, $this->clinic_id, (int) $this->realtime_auth_version);
            }

            return $saved;
        });
    }

    public function delete(): ?bool
    {
        return DB::transaction(function (): ?bool {
            if ($this->exists) {
                $current = DB::table('users')->where('id', $this->id)->lockForUpdate()->firstOrFail();
                app(AccessMutations::class)->record($this->id, $this->clinic_id, (int) $current->realtime_auth_version + 1);
            }

            return parent::delete();
        });
    }

    public function assignRole(...$roles): static
    {
        return app(AccessMutations::class)->change($this, fn () => $this->assignRoleWithoutRevocation(...$roles));
    }

    public function removeRole(...$roles): static
    {
        return app(AccessMutations::class)->change($this, fn () => $this->removeRoleWithoutRevocation(...$roles));
    }

    public function syncRoles(...$roles): static
    {
        return app(AccessMutations::class)->change($this, fn () => $this->syncRolesWithoutRevocation(...$roles));
    }

    public function givePermissionTo(...$permissions): static
    {
        return app(AccessMutations::class)->change($this, fn () => $this->givePermissionWithoutRevocation(...$permissions));
    }

    public function revokePermissionTo($permission): static
    {
        return app(AccessMutations::class)->change($this, fn () => $this->revokePermissionWithoutRevocation($permission));
    }

    public function syncPermissions(...$permissions): static
    {
        return app(AccessMutations::class)->change($this, fn () => $this->syncPermissionsWithoutRevocation(...$permissions));
    }

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
            'terms_accepted_at' => 'datetime',
            'account_type' => UserAccountType::class,
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
