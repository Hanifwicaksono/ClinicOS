<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

require getenv('AUDIT_ROOT').'/application.php';
$app->make(Kernel::class)->bootstrap();
$dir = getenv('PROBE_ROOT');
if (! str_starts_with($dir, getenv('AUDIT_ROOT').'/probe-')) {
    throw new RuntimeException('Private notification probe guard');
}
if (in_array(getenv('PROBE_MODE'), ['deferred', 'immediate'], true)) {
    config(['database.connections.sqlite.transaction_mode' => getenv('PROBE_MODE') === 'immediate' ? 'IMMEDIATE' : 'DEFERRED']);
}
if (DB::getDriverName() === 'sqlite') {
    DB::statement('PRAGMA journal_mode=WAL');
    DB::statement('PRAGMA busy_timeout=5000');
}
$action = $argv[1];
if ($action === 'setup') {
    $payload = json_decode(DB::table('failed_jobs')->where('payload', 'like', '%DeliverClinicNotification%')->firstOrFail()->payload, true, flags: JSON_THROW_ON_ERROR);
    $payload['uuid'] = (string) Str::uuid();
    Queue::connection('database')->pushRaw(json_encode($payload, JSON_THROW_ON_ERROR));
    file_put_contents($dir.'/job-id', $payload['uuid']);
} elseif ($action === 'writer') {
    file_put_contents($dir.'/writer-ready', (string) microtime(true));
    DB::table('users')->where('id', 13)->update(['updated_at' => now()]);
    file_put_contents($dir.'/writer-committed', (string) microtime(true));
} elseif ($action === 'worker' || $action === 'recovery') {
    $held = false;
    if ($action === 'worker') {
        DB::listen(function (QueryExecuted $query) use ($dir, &$held): void {
            if ($held || ! str_starts_with(strtolower($query->sql), 'select') || ! preg_match('/from ["`]jobs["`]/', $query->sql)) {
                return;
            }
            $held = true;
            file_put_contents($dir.'/reader-ready', (string) microtime(true));
            $deadline = microtime(true) + 15;
            while (! is_file($dir.'/release-reader') && microtime(true) < $deadline) {
                clearstatcache();
                usleep(10000);
            }
            if (! is_file($dir.'/release-reader')) {
                throw new RuntimeException('Notification probe barrier timeout');
            }
        });
    } else {
        Artisan::call('queue:retry', ['id' => [trim(file_get_contents($dir.'/job-id'))]]);
    }
    Event::listen(JobProcessing::class, function (JobProcessing $event) use ($dir): void {
        file_put_contents($dir.'/processed', $event->job->resolveName()."\n", FILE_APPEND);
    });
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0]);
}
$id = is_file($dir.'/job-id') ? trim(file_get_contents($dir.'/job-id')) : '';
$failed = DB::table('failed_jobs')->where('uuid', $id)->first();
echo json_encode(['action' => $action, 'notifications' => DB::table('notifications')->count(), 'pending_jobs' => DB::table('jobs')->count(),
    'failed_target' => $failed !== null, 'locked' => $failed && str_contains($failed->exception, 'database is locked'),
    'processed' => is_file($dir.'/processed') ? count(file($dir.'/processed')) : 0], JSON_THROW_ON_ERROR);
