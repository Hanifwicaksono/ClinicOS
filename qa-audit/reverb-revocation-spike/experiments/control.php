<?php

use App\Models\Appointment;
use App\Models\Queue;
use App\Models\User;
use App\Services\StaffAccountService;
use GuzzleHttp\Client;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RevocationSpike\State;
use Spatie\Permission\Models\Role;

require getenv('AUDIT_ROOT').'/application.php';
$app->make(Kernel::class)->bootstrap();

$command = $argv[1] ?? '';
$target = User::where('email', 'closure.dental.doctor@clinicos.test')->firstOrFail();
$doctorRole = Role::findByName('Doctor');
$result = [];
if ($command === 'setup') {
    DB::statement('PRAGMA journal_mode=WAL');
    if (! DB::getSchemaBuilder()->hasTable('spike_authority_versions')) {
        DB::getSchemaBuilder()->create('spike_authority_versions', function ($table): void {
            $table->unsignedBigInteger('user_id')->primary();
            $table->unsignedBigInteger('version')->default(1);
        });
    }
    foreach (User::pluck('id') as $id) {
        DB::table('spike_authority_versions')->insertOrIgnore(['user_id' => $id, 'version' => 1]);
    }
    $oldJobs = DB::table('jobs')->count();
    DB::table('jobs')->delete();
    $queues = [];
    for ($index = 0; $index < 80; $index++) {
        $appointment = Appointment::factory()->create(['clinic_id' => 6, 'patient_id' => 13, 'doctor_id' => 3,
            'service_id' => 5, 'doctor_schedule_id' => 21, 'appointment_date' => '2026-11-14']);
        $number = 500 + $index;
        $queue = Queue::factory()->create(['clinic_id' => 6, 'appointment_id' => $appointment->id, 'doctor_id' => 3,
            'doctor_schedule_id' => 21, 'queue_date' => '2026-11-14', 'queue_number' => $number, 'display_number' => 'S1-'.$number]);
        $queues[] = $queue->id;
    }
    $result = ['target' => $target->id, 'control' => User::where('email', 'closure.dental.reception@clinicos.test')->value('id'),
        'patient' => User::where('email', 'closure.dental.patient@clinicos.test')->value('id'),
        'foreign' => User::where('email', 'closure.prorata.reception@clinicos.test')->value('id'),
        'clinic' => 6, 'queues' => $queues, 'discarded_synthetic_pending_jobs' => $oldJobs, 'historical_rows_deleted' => false];
} elseif ($command === 'restore') {
    DB::transaction(function () use ($target, $doctorRole): void {
        $doctorRole->givePermissionTo('queue.view');
        $target->syncRoles(['Doctor']);
        $target->syncPermissions([]);
        preg_match("/Hash::make\\('([^']+)'\\)/", file_get_contents(getenv('AUDIT_REPO').'/database/seeders/DemoUserSeeder.php'), $match);
        $target->forceFill(['is_active' => true] + (Hash::check($match[1], $target->password) ? [] : ['password' => Hash::make($match[1])]))->save();
        $target->doctor?->update(['is_active' => true]);
    });
} elseif ($command === 'role') {
    DB::transaction(fn () => $target->removeRole('Doctor'));
} elseif ($command === 'permission') {
    DB::transaction(fn () => $doctorRole->revokePermissionTo('queue.view'));
} elseif ($command === 'direct-setup') {
    DB::transaction(function () use ($doctorRole, $target): void {
        $doctorRole->revokePermissionTo('queue.view');
        $target->givePermissionTo('queue.view');
    });
} elseif ($command === 'direct') {
    DB::transaction(fn () => $target->revokePermissionTo('queue.view'));
} elseif (in_array($command, ['inactive', 'password'], true)) {
    $owner = User::where('email', 'closure.dental.owner@clinicos.test')->firstOrFail();
    Auth::login($owner);
    $data = ['name' => $target->name, 'email' => $target->email, 'phone' => $target->phone,
        'is_active' => $command !== 'inactive', 'specialization' => $target->doctor->specialization,
        'service_ids' => $target->doctor->services->pluck('id')->all()];
    if ($command === 'password') {
        $data['password'] = bin2hex(random_bytes(24));
    }
    app(StaffAccountService::class)->update($owner, $target, $data);
} elseif ($command === 'foreign-fixture') {
    $appointment = Appointment::factory()->create(['clinic_id' => 14, 'patient_id' => 21, 'doctor_id' => 11,
        'service_id' => 13, 'doctor_schedule_id' => 77, 'appointment_date' => '2026-11-14']);
    $queue = Queue::factory()->create(['clinic_id' => 14, 'appointment_id' => $appointment->id, 'doctor_id' => 11,
        'doctor_schedule_id' => 77, 'queue_date' => '2026-11-14', 'queue_number' => 950, 'display_number' => 'S1-F950']);
    $result = ['queue_id' => $queue->id];
} elseif (in_array($command, ['terminate', 'terminate-failure', 'terminate-unreachable'], true)) {
    try {
        $pusher = Broadcast::connection('reverb')->getPusher();
        if ($command === 'terminate-failure') {
            $options = config('broadcasting.connections.reverb.options');
            $pusher = new Pusher\Pusher(config('broadcasting.connections.reverb.key'), 'intentionally-wrong-dummy-secret', config('broadcasting.connections.reverb.app_id'), $options);
        } elseif ($command === 'terminate-unreachable') {
            $options = [...config('broadcasting.connections.reverb.options'), 'port' => 1];
            $client = new Client(['timeout' => 0.5, 'connect_timeout' => 0.3]);
            $pusher = new Pusher\Pusher(config('broadcasting.connections.reverb.key'), config('broadcasting.connections.reverb.secret'), config('broadcasting.connections.reverb.app_id'), $options, $client);
        }
        $response = $pusher->terminateUserConnections((string) $target->id);
        $result = ['accepted' => true, 'response_type' => get_debug_type($response)];
    } catch (Throwable $exception) {
        $result = ['accepted' => false, 'exception' => $exception::class, 'code' => $exception->getCode(),
            'cause' => $exception->getPrevious() ? $exception->getPrevious()::class : null];
    }
} elseif ($command === 'rollback') {
    $before = State::context($target->id)['version'];
    DB::beginTransaction();
    $target->removeRole('Doctor');
    DB::rollBack();
    $result = ['version_unchanged' => State::context($target->id)['version'] === $before];
} else {
    throw new RuntimeException('Unknown isolated spike command');
}
$context = State::context($target->id);
$result += ['command' => $command, 'barrier' => microtime(true), 'target_active' => $context['active'],
    'target_has_role' => in_array('Doctor', $context['roles'], true), 'target_queue_permission' => in_array('queue.view', $context['permissions'], true), 'epoch' => $context['version']];
echo json_encode($result, JSON_THROW_ON_ERROR);
