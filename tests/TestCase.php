<?php

namespace Tests;

use App\Models\Scopes\ClinicScope;
use App\Models\Visit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, mixed $guard = null): static
    {
        parent::actingAs($user, $guard);
        $guardName = $guard ?? $this->app['auth']->getDefaultDriver();
        // Match the session hash established by SessionGuard::login in real requests.
        $this->withSession(['password_hash_'.$guardName => $this->app['auth']->guard($guardName)->hashPasswordForCookie($user->getAuthPassword())]);

        return $this;
    }

    /** @return array{record_version: int, allergy_version: string} */
    protected function recordVersions(Visit $visit): array
    {
        return [
            'record_version' => (int) $visit->medicalRecord()->value('lock_version'),
            'allergy_version' => $visit->patient()->withoutGlobalScope(ClinicScope::class)
                ->where('clinic_id', $visit->clinic_id)->firstOrFail()->allergyVersion(),
        ];
    }
}
