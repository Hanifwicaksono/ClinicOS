<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RevocationSpike\State;
use Spatie\Permission\Models\Role;

require getenv('AUDIT_ROOT').'/application.php';
$app->make(Kernel::class)->bootstrap();

$results = [];
function verify(string $name, callable $body): void
{
    global $results;
    DB::beginTransaction();
    try {
        if ($body() !== true) {
            throw new RuntimeException('Assertion failed');
        }
        $results[] = ['name' => $name, 'status' => 'PASS'];
    } catch (Throwable $error) {
        $results[] = ['name' => $name, 'status' => 'FAIL', 'class' => $error::class];
    } finally {
        DB::rollBack();
    }
}
$context = State::context(14);
$identity = ['user_id' => '14', 'context_version' => $context['version']];
verify('Current queue and own notification context allowed', fn (): bool => State::allowed('private-clinic.6.queues', $identity) && State::allowed('private-users.14', $identity));
verify('Foreign tenant denied', fn (): bool => ! State::allowed('private-clinic.14.queues', $identity));
verify('Foreign user notification denied', fn (): bool => ! State::allowed('private-users.45', $identity));
verify('Unknown private family denied', fn (): bool => ! State::allowed('private-cache-spike', $identity));
verify('Unsigned legacy binding denied', fn (): bool => ! State::allowed('private-clinic.6.queues', []));
verify('Empty authorization version denied', fn (): bool => ! State::allowed('private-clinic.6.queues', ['user_id' => '14', 'context_version' => '']));
verify('Old authorization version denied', fn (): bool => ! State::allowed('private-clinic.6.queues', ['user_id' => '14', 'context_version' => '0']));
verify('Missing durable authority row denied', function () use ($identity): bool {
    DB::table('spike_authority_versions')->where('user_id', 14)->delete();

    return ! State::allowed('private-clinic.6.queues', $identity)
        && ! State::allowed('private-clinic.6.queues', ['user_id' => '14', 'context_version' => '']);
});
verify('Inactive update bumps epoch in same transaction', function () use ($context, $identity): bool {
    User::findOrFail(14)->update(['is_active' => false]);

    return (int) State::context(14)['version'] > (int) $context['version'] && ! State::allowed('private-users.14', $identity);
});
verify('Password update bumps epoch and invalidates old socket context', function () use ($context, $identity): bool {
    User::findOrFail(14)->forceFill(['password' => Hash::make('disposable-backend-check')])->save();

    return (int) State::context(14)['version'] > (int) $context['version'] && ! State::allowed('private-users.14', $identity);
});
verify('Clinic reassignment bumps epoch and denies original clinic', function () use ($context, $identity): bool {
    User::findOrFail(14)->update(['clinic_id' => 14]);

    return (int) State::context(14)['version'] > (int) $context['version'] && ! State::allowed('private-clinic.6.queues', $identity);
});
verify('Role permission change fans out to all role members, not receptionist', function () use ($context): bool {
    $before = State::context(13)['version'];
    Role::findByName('Doctor')->revokePermissionTo('queue.view');

    return (int) State::context(14)['version'] > (int) $context['version']
        && State::context(13)['version'] === $before && ! in_array('queue.view', State::context(14)['permissions'], true);
});
verify('Direct permission removal does not erase inherited role permission', function (): bool {
    $user = User::findOrFail(14);
    $user->givePermissionTo('queue.view');
    $user->revokePermissionTo('queue.view');

    return in_array('queue.view', State::context(14)['permissions'], true);
});
verify('syncRoles invalidates old authority', function () use ($identity): bool {
    User::findOrFail(14)->syncRoles([]);

    return ! State::allowed('private-users.14', $identity) && ! State::allowed('private-clinic.6.queues', $identity);
});
verify('Rollback preserves durable user state and epoch', fn (): bool => State::context(14)['version'] === $context['version'] && State::context(14)['active'] && State::context(14)['clinic_id'] === 6);
echo json_encode(['kind' => 'actual isolated backend checks, not PHPUnit full suite', 'results' => $results,
    'PASS' => count(array_filter($results, fn (array $result): bool => $result['status'] === 'PASS')),
    'FAIL' => count(array_filter($results, fn (array $result): bool => $result['status'] === 'FAIL'))], JSON_THROW_ON_ERROR);
