<?php

use App\Models\Clinic;
use App\Models\RealtimeRevocation;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

if (getenv('DB_PORT') !== '13318' || ! str_starts_with(getenv('DB_DATABASE'), 'clinicos_s2_')) {
    throw new RuntimeException('Dedicated MySQL fixture guard');
}
require getenv('AUDIT_REPO').'/vendor/autoload.php';
$app = require getenv('AUDIT_REPO').'/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix('C:');
$app->make(Kernel::class)->bootstrap();
$action = $argv[1];
$barrier = getenv('S2_CONCURRENCY_ROOT');
if (! str_starts_with($barrier, getenv('AUDIT_ROOT').'/mysql/concurrency-')) {
    throw new RuntimeException('Private concurrency barrier guard');
}
if ($action === 'setup') {
    (new RoleAndPermissionSeeder)->run();
    $user = User::factory()->create(['clinic_id' => Clinic::factory()->create()->id]);
    $user->assignRole('Doctor');
    echo json_encode(['user_id' => $user->id, 'version' => $user->fresh()->realtime_auth_version]);
} elseif (in_array($action, ['a', 'b'], true)) {
    $user = User::findOrFail((int) $argv[2]);
    $before = (int) $user->realtime_auth_version;
    file_put_contents($barrier.'/ready-'.$action, (string) $before);
    $deadline = microtime(true) + 15;
    while (! is_file($barrier.'/release') && microtime(true) < $deadline) {
        usleep(10000);
        clearstatcache();
    }
    if (! is_file($barrier.'/release')) {
        throw new RuntimeException('Concurrency barrier timeout');
    }
    if ($action === 'a') {
        $user->update(['is_active' => false]);
    } else {
        $user->update(['password' => Hash::make('isolated-concurrent-password')]);
    }
    echo json_encode(['writer' => $action, 'loaded_epoch' => $before, 'saved_epoch' => (int) $user->realtime_auth_version]);
} elseif ($action === 'verify') {
    $user = User::findOrFail((int) $argv[2]);
    $versions = RealtimeRevocation::where('user_id', $user->id)->orderBy('auth_version')->pluck('auth_version')->all();
    $before = (int) $user->realtime_auth_version;
    $count = RealtimeRevocation::where('user_id', $user->id)->count();
    $jobs = DB::table('jobs')->count();
    try {
        DB::transaction(function () use ($user): void {
            $user->update(['is_active' => true]);
            $user->removeRole('Doctor');
            throw new RuntimeException('Intentional MySQL rollback');
        });
    } catch (RuntimeException) {
    }
    $fresh = $user->fresh();
    echo json_encode(['version' => (int) $fresh->realtime_auth_version, 'versions' => $versions, 'active' => $fresh->is_active,
        'password_changed' => Hash::check('isolated-concurrent-password', $fresh->password),
        'rollback_valid' => (int) $fresh->realtime_auth_version === $before && ! $fresh->is_active && $fresh->hasRole('Doctor')
            && RealtimeRevocation::where('user_id', $user->id)->count() === $count && DB::table('jobs')->count() === $jobs]);
}
