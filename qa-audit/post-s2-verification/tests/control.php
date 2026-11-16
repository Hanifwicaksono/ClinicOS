<?php

use App\Jobs\RevokeRealtimeAccess;
use App\Models\Appointment;
use App\Models\Queue;
use App\Models\RealtimeRevocation;
use App\Models\Role;
use App\Models\User;
use App\Services\Realtime\AuthorizationContext;
use App\Services\StaffAccountService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require getenv('AUDIT_ROOT').'/application.php';
$app->make(Kernel::class)->bootstrap();
$command = $argv[1] ?? '';
$target = User::where('email', 'closure.dental.doctor@clinicos.test')->firstOrFail();
$result = [];
if ($command === 'setup') {

    DB::table('jobs')->delete();
    $queues = [];
    for ($index = 0; $index < 80; $index++) {
        $appointment = Appointment::factory()->create(['clinic_id' => 6, 'patient_id' => 13, 'doctor_id' => 3, 'service_id' => 5,
            'doctor_schedule_id' => 21, 'appointment_date' => '2026-11-14']);
        $queue = Queue::factory()->create(['clinic_id' => 6, 'appointment_id' => $appointment->id, 'doctor_id' => 3,
            'doctor_schedule_id' => 21, 'queue_date' => '2026-11-14', 'queue_number' => 700 + $index, 'display_number' => 'S2-'.(700 + $index)]);
        $queues[] = $queue->id;
    }
    $result = ['target' => $target->id, 'control' => 13, 'patient' => 15, 'foreign' => 45, 'clinic' => 6, 'queues' => $queues];
} elseif ($command === 'restore') {
    DB::transaction(function () use ($target): void {
        Role::findByName('Doctor')->givePermissionTo('queue.view');
        $target->syncRoles(['Doctor']);
        $target->syncPermissions([]);
        preg_match("/Hash::make\\('([^']+)'\\)/", file_get_contents(getenv('AUDIT_REPO').'/database/seeders/DemoUserSeeder.php'), $match);
        $target->forceFill(['is_active' => true] + (Hash::check($match[1], $target->password) ? [] : ['password' => Hash::make($match[1])]))->save();
        $target->doctor->update(['is_active' => true]);
    });
} elseif ($command === 'role') {
    $target->removeRole('Doctor');
} elseif ($command === 'permission') {
    Role::findByName('Doctor')->revokePermissionTo('queue.view');
} elseif ($command === 'direct-setup') {
    DB::transaction(function () use ($target): void {
        Role::findByName('Doctor')->revokePermissionTo('queue.view');
        $target->givePermissionTo('queue.view');
    });
} elseif ($command === 'direct') {
    $target->revokePermissionTo('queue.view');
} elseif (in_array($command, ['inactive', 'password'], true)) {
    $owner = User::where('email', 'closure.dental.owner@clinicos.test')->firstOrFail();
    Auth::login($owner);
    $data = ['name' => $target->name, 'email' => $target->email, 'phone' => $target->phone, 'is_active' => $command !== 'inactive',
        'specialization' => $target->doctor->specialization, 'service_ids' => $target->doctor->services->pluck('id')->all()];
    if ($command === 'password') {
        $data['password'] = bin2hex(random_bytes(24));
    }
    app(StaffAccountService::class)->update($owner, $target, $data);
} elseif ($command === 'rollback') {
    DB::beginTransaction();
    $target->update(['is_active' => false]);
    $target->removeRole('Doctor');
    DB::rollBack();
} elseif ($command === 'control') {
    $revocation = RealtimeRevocation::where('user_id', $target->id)->orderByDesc('auth_version')->firstOrFail();
    try {
        (new RevokeRealtimeAccess($revocation->id))->handle();
        $result['control_success'] = true;
    } catch (Throwable $error) {
        $result += ['control_success' => false, 'error' => $error::class];
    }
} elseif ($command === 'drain') {
    foreach (RealtimeRevocation::whereNull('acknowledged_at')->orderBy('auth_version')->get() as $revocation) {
        try {
            (new RevokeRealtimeAccess($revocation->id))->handle();
        } catch (Throwable) {
            // Setup waits on the durable ACK separately; a successful HTTP response may precede socket close.
        }
    }
} elseif ($command === 'foreign-fixture') {
    $appointment = Appointment::factory()->create(['clinic_id' => 14, 'patient_id' => 21, 'doctor_id' => 11,
        'service_id' => 13, 'doctor_schedule_id' => 77, 'appointment_date' => '2026-11-14']);
    $number = (int) Queue::where('clinic_id', 14)->max('queue_number') + 100;
    $queue = Queue::factory()->create(['clinic_id' => 14, 'appointment_id' => $appointment->id, 'doctor_id' => 11,
        'doctor_schedule_id' => 77, 'queue_date' => '2026-11-14', 'queue_number' => $number, 'display_number' => 'S2-F'.$number]);
    $result = ['queue_id' => $queue->id];
} elseif ($command === 'legacy') {
    $result = json_decode(Broadcast::connection('reverb')->getPusher()
        ->authorizeChannel($argv[3] ?? 'private-clinic.6.queues', $argv[2]), true, flags: JSON_THROW_ON_ERROR);
} else {
    throw new RuntimeException('Unknown S2 fixture command');
}
$context = app(AuthorizationContext::class)->read($target->id);
$request = RealtimeRevocation::where('user_id', $target->id)->orderByDesc('auth_version')->first();
echo json_encode($result + ['command' => $command, 'committed_at' => microtime(true), 'epoch' => $context['version'],
    'active' => $context['active'], 'role' => in_array('Doctor', $context['roles'], true), 'queue_permission' => in_array('queue.view', $context['permissions'], true),
    'request_id' => $request?->id, 'acknowledged' => $request?->acknowledged_at !== null, 'failed' => $request?->failed_at !== null], JSON_THROW_ON_ERROR);
