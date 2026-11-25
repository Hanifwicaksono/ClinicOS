<?php

namespace Tests;

use App\Models\Scopes\ClinicScope;
use App\Models\Visit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['db']->connection($connection)->getDatabaseName();

        if (! $app->environment('testing') || ! (
            ($connection === 'sqlite' && $database === ':memory:')
            || ($connection === 'mysql' && $database === 'clinicos_testing')
        )) {
            throw new RuntimeException('Tests require an uncached testing configuration and an isolated test database. No migrations were run.');
        }

        return $app;
    }

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
