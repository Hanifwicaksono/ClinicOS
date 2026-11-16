<?php

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Models\SubscriptionReminder;
use App\Models\User;
use App\Models\Visit;
use App\PaymentStatus;
use App\Services\BookingService;
use App\Services\DokuPaymentService;
use App\Services\QueueWorkflowService;
use App\Services\StaffAccountService;
use App\Services\SubscriptionService;
use App\Services\VisitWorkflowService;
use App\UserAccountType;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

$app = require __DIR__.'/application.php';
$app->make(Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true) ?? [];
$action = $argv[1];

/** @return array<string, mixed> */
function fixture(string $label): array
{
    $clinic = Clinic::factory()->create(['name' => 'Audit '.$label, 'slug' => 'audit-'.Str::lower(Str::random(12)), 'email' => 'clinic@clinicos.test']);
    ClinicSetting::factory()->for($clinic)->create(['opening_time' => '00:00', 'closing_time' => '23:59']);
    $users = [];
    foreach (['owner' => 'Clinic Admin', 'admin' => 'Clinic Admin', 'receptionist' => 'Receptionist', 'receptionist2' => 'Receptionist',
        'doctor' => 'Doctor', 'doctor2' => 'Doctor', 'patient' => 'Patient', 'patient2' => 'Patient'] as $name => $role) {
        $user = User::factory()->forClinic($clinic)->create(['name' => 'Audit '.$name, 'email' => Str::lower(Str::random(12)).'@clinicos.test']);
        $user->assignRole($role);
        $users[$name] = ['id' => $user->id, 'email' => $user->email];
        if ($name === 'owner') {
            $user->update(['account_type' => UserAccountType::ClinicOwner]);
            $clinic->update(['owner_user_id' => $user->id]);
        }
        if ($role === 'Patient') {
            $user->update(['account_type' => UserAccountType::Patient]);
            $patient = Patient::factory()->for($clinic)->create(['user_id' => $user->id, 'name' => 'Audit patient', 'nik' => null, 'email' => $user->email]);
            $users[$name]['patient_id'] = $patient->id;
        }
        if ($role === 'Doctor') {
            $doctor = Doctor::factory()->for($clinic)->for($user, 'user')->create(['specialization' => 'Dokter Umum']);
            $users[$name]['doctor_id'] = $doctor->id;
        }
    }
    app(SubscriptionService::class)->startTrial($clinic);
    $service = Service::factory()->for($clinic)->create(['name' => 'Konsultasi audit', 'price' => 75000]);
    foreach (['doctor', 'doctor2'] as $name) {
        $doctor = Doctor::findOrFail($users[$name]['doctor_id']);
        $doctor->services()->attach($service);
        $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => now('Asia/Jakarta')->dayOfWeek, 'start_time' => '00:00', 'end_time' => '23:59', 'quota' => 500]);
        $users[$name]['schedule_id'] = $schedule->id;
    }

    return ['clinic_id' => $clinic->id, 'slug' => $clinic->slug, 'service_id' => $service->id, 'date' => now('Asia/Jakarta')->toDateString(), 'users' => $users];
}

/** @return array<string, mixed> */
function bookingPayload(array $fixture): array
{
    return ['doctor_id' => $fixture['users']['doctor']['doctor_id'], 'doctor_schedule_id' => $fixture['users']['doctor']['schedule_id'],
        'service_id' => $fixture['service_id'], 'appointment_date' => $fixture['date'], 'idempotency_key' => (string) Str::uuid(),
        'name' => 'Synthetic audit patient', 'birth_date' => '1990-01-01', 'gender' => 'MALE', 'phone' => '08'.random_int(1000000000, 9999999999)];
}

/** @return array<string, mixed> */
function versions(Visit $visit): array
{
    return ['record_version' => $visit->medicalRecord()->value('lock_version'), 'allergy_version' => $visit->patient->allergyVersion()];
}

/** @return array<string, mixed> */
function executeTask(array $task): array
{
    if ($task['type'] === 'http') {
        $actor = User::findOrFail($task['actor']);
        Auth::guard()->setUser($actor);
        $request = Request::create(getenv('APP_URL').$task['path'], $task['method'] ?? 'GET', $task['payload'] ?? [], [], [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_HOST' => parse_url(getenv('APP_URL'), PHP_URL_HOST).':'.parse_url(getenv('APP_URL'), PHP_URL_PORT)]);
        $kernel = app(Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $json = json_decode($response->getContent(), true);

        return ['status' => $response->getStatusCode(), 'validation_fields' => array_keys($json['errors'] ?? []),
            'redirect_path' => parse_url($response->headers->get('Location', ''), PHP_URL_PATH)];
    }
    if ($task['type'] === 'deactivate') {
        app(StaffAccountService::class)->update(User::findOrFail($task['actor']), User::findOrFail($task['staff']), ['is_active' => false]);

        return ['status' => 200];
    }
    if ($task['type'] === 'downgrade') {
        app(SubscriptionService::class)->downgradeToFree(Clinic::findOrFail($task['clinic_id']), 'Isolated readiness concurrency audit');

        return ['status' => 200];
    }
    throw new RuntimeException('Unsupported audit task');
}

try {
    if ($action === 'fixture') {
        (new RoleAndPermissionSeeder)->run();
        (new SubscriptionPlanSeeder)->run();
        $main = fixture('main');
        $other = fixture('other');
        $platform = User::where('email', 'platform-audit@clinicos.test')->first()
            ?? User::factory()->platformAdmin()->create(['email' => 'platform-audit@clinicos.test']);
        $result = ['main' => $main, 'other' => $other, 'platform' => ['id' => $platform->id, 'email' => $platform->email]];
        file_put_contents(getenv('AUDIT_ROOT').'/fixture.json', json_encode($result, JSON_THROW_ON_ERROR));
    } elseif ($action === 'race-fixture') {
        $fixture = fixture($input['case']);
        $payload = bookingPayload($fixture);
        $result = ['fixture' => $fixture, 'payload' => $payload];
        if ($input['case'] === 'schedule-edit') {
            DoctorSchedule::findOrFail($fixture['users']['doctor2']['schedule_id'])->update(['day_of_week' => (now('Asia/Jakarta')->dayOfWeek + 1) % 7]);
        }
        if (in_array($input['case'], ['last-slot', 'patient-contention'], true)) {
            DoctorSchedule::findOrFail($payload['doctor_schedule_id'])->update(['quota' => 1]);
        }
        if (in_array($input['case'], ['same-record', 'two-doctors', 'subscription-active', 'owner-checkout'], true)) {
            $actor = User::findOrFail($fixture['users']['receptionist']['id']);
            $appointment = app(BookingService::class)->book(Clinic::findOrFail($fixture['clinic_id']), $payload, $actor, 'RECEPTIONIST')['appointment'];
            $queue = app(QueueWorkflowService::class)->checkIn($appointment->queue, $actor);
            app(QueueWorkflowService::class)->call($queue, $actor);
            $visit = app(VisitWorkflowService::class)->start($queue, User::findOrFail($fixture['users']['doctor']['id']))['visit'];
            $result['visit_id'] = $visit->id;
            $result['versions'] = versions($visit);
        }
        if ($input['case'] === 'owner-checkout') {
            $result['price_id'] = SubscriptionPrice::whereHas('plan', fn ($query) => $query->where('code', 'individual'))->where('duration_months', 1)->value('id');
        }
    } elseif ($action === 'worker') {
        $connection = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
        file_put_contents($input['ready'], json_encode(['pid' => getmypid(), 'connection_id' => $connection]));
        $deadline = microtime(true) + 20;
        while (! is_file($input['barrier']) && microtime(true) < $deadline) {
            usleep(5000);
        }
        if (! is_file($input['barrier'])) {
            throw new RuntimeException('Barrier timeout');
        }
        $held = false;
        if (! empty($input['hold_lock_ms'])) {
            DB::listen(function (QueryExecuted $query) use (&$held, $input): void {
                if (! $held && str_contains($query->sql, 'for update') && str_contains($query->sql, 'clinics')) {
                    $held = true;
                    usleep($input['hold_lock_ms'] * 1000);
                }
            });
        }
        $start = microtime(true);
        $result = executeTask($input['task']);
        $result += ['pid' => getmypid(), 'connection_id' => $connection, 'started' => $start, 'ended' => microtime(true)];
    } elseif ($action === 'task') {
        $result = executeTask($input);
    } elseif ($action === 'state') {
        $clinicId = $input['clinic_id'];
        $result = ['appointments' => Appointment::where('clinic_id', $clinicId)->count(), 'queues' => Queue::where('clinic_id', $clinicId)->count(),
            'visits' => Visit::where('clinic_id', $clinicId)->count(), 'queue_numbers' => Queue::where('clinic_id', $clinicId)->pluck('queue_number')->all(),
            'mismatched_doctor' => DB::table('appointments')->join('doctor_schedules', 'appointments.doctor_schedule_id', '=', 'doctor_schedules.id')
                ->where('appointments.clinic_id', $clinicId)->whereColumn('appointments.doctor_id', '!=', 'doctor_schedules.doctor_id')->count(),
            'plan' => Subscription::where('clinic_id', $clinicId)->first()?->plan?->code,
            'pending_payments' => Payment::where('clinic_id', $clinicId)->where('status', PaymentStatus::Pending)->count(),
            'visits_state' => Visit::where('clinic_id', $clinicId)->with('medicalRecord')->get()->map(fn ($v) => ['id' => $v->id, 'status' => $v->status->value, 'version' => $v->medicalRecord->lock_version])->all()];
    } elseif ($action === 'revoke') {
        User::findOrFail($input['user_id'])->syncRoles([]);
        $result = ['revoked' => true];
    } elseif ($action === 'expire-session') {
        $result = ['expired' => DB::table('sessions')->where('user_id', $input['user_id'])->update(['last_activity' => time() - 10800])];
    } elseif ($action === 'main-state') {
        $fixture = json_decode(file_get_contents(getenv('AUDIT_ROOT').'/fixture.json'), true);
        $result = ['appointments' => Appointment::where('clinic_id', $fixture['main']['clinic_id'])->orderBy('id')->get(['id', 'patient_id', 'status'])->toArray(),
            'queues' => Queue::where('clinic_id', $fixture['main']['clinic_id'])->orderBy('id')->get(['id', 'appointment_id', 'status'])->toArray(),
            'visits' => Visit::where('clinic_id', $fixture['main']['clinic_id'])->with('medicalRecord')->get()->map(fn ($visit) => ['id' => $visit->id,
                'status' => $visit->status->value, 'record_status' => $visit->medicalRecord->status->value,
                'version' => $visit->medicalRecord->lock_version, 'photo_count' => count($visit->medicalRecord->photos ?? []),
                'revision_count' => $visit->medicalRecord->revisions()->count()])->all(),
            'jobs' => DB::table('jobs')->count(), 'failed_jobs' => DB::table('failed_jobs')->count()];
    } elseif ($action === 'staff-id') {
        $user = User::where('email', $input['email'])->firstOrFail();
        $result = ['id' => $user->id, 'active' => $user->is_active, 'verified' => $user->hasVerifiedEmail()];
    } elseif ($action === 'load-data') {
        $clinic = Clinic::findOrFail($input['clinic_id']);
        Patient::factory()->count(2000)->for($clinic)->create(['nik' => null, 'email' => null, 'user_id' => null]);
        $result = ['patients' => Patient::withoutGlobalScopes()->where('clinic_id', $clinic->id)->count()];
    } elseif ($action === 'diagnose') {
        $result = ['environment' => app()->environment(), 'mail_driver' => config('mail.default'),
            'mail_host' => config('mail.mailers.smtp.host'), 'mail_port' => config('mail.mailers.smtp.port'),
            'mail_scheme' => config('mail.mailers.smtp.scheme'), 'mail_url_set' => filled(config('mail.mailers.smtp.url')),
            'storage_isolated' => str_starts_with(storage_path(), getenv('AUDIT_ROOT')), 'log_channel' => config('logging.default')];
    } elseif ($action === 'send-verification') {
        User::findOrFail($input['user_id'])->sendEmailVerificationNotification();
        $result = ['sent' => true];
    } elseif ($action === 'transaction-rollback') {
        $clinic = Clinic::findOrFail($input['clinic_id']);
        $tables = ['patients', 'appointments', 'queues', 'notifications', 'jobs', 'audit_logs'];
        $before = array_combine($tables, array_map(fn ($table) => DB::table($table)->count(), $tables));
        try {
            DB::transaction(function () use ($clinic, $input): void {
                app(BookingService::class)->book($clinic, $input['payload'], User::findOrFail($input['actor']), 'RECEPTIONIST');
                throw new RuntimeException('Audit injected transaction failure');
            });
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'Audit injected transaction failure') {
                throw $exception;
            }
        }
        $after = array_combine($tables, array_map(fn ($table) => DB::table($table)->count(), $tables));
        $result = ['before' => $before, 'after' => $after, 'rolled_back' => $before === $after, 'connection_usable' => DB::selectOne('SELECT 1 AS ok')->ok === 1];
    } elseif ($action === 'storage-failure') {
        $visit = Visit::findOrFail($input['visit_id']);
        $beforeVersion = $visit->medicalRecord->lock_version;
        $blocker = getenv('AUDIT_ROOT').'/storage-blocker';
        file_put_contents($blocker, 'Audit file prevents directory creation');
        config(['filesystems.disks.local.root' => $blocker]);
        Storage::forgetDisk('local');
        $failed = false;
        try {
            app(VisitWorkflowService::class)->saveDraft($visit, User::findOrFail($input['actor']), versions($visit) + [
                'subjective' => 'Synthetic storage failure', 'photos' => [UploadedFile::fake()->image('audit.png')],
            ]);
        } catch (Throwable $exception) {
            $failed = true;
        }
        $result = ['filesystem_failure_observed' => $failed, 'version_unchanged' => $visit->medicalRecord()->value('lock_version') === $beforeVersion,
            'photo_count' => count($visit->medicalRecord()->firstOrFail()->photos ?? [])];
    } elseif ($action === 'schema') {
        $foreignKeys = DB::select('SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION');
        $groups = [];
        foreach ($foreignKeys as $key) {
            $groups[$key->TABLE_NAME.'.'.$key->CONSTRAINT_NAME][] = $key;
        }
        $orphans = [];
        foreach ($groups as $name => $keys) {
            $key = $keys[0];
            $join = implode(' AND ', array_map(fn ($k) => 'c.`'.$k->COLUMN_NAME.'` = p.`'.$k->REFERENCED_COLUMN_NAME.'`', $keys));
            $notNull = implode(' AND ', array_map(fn ($k) => 'c.`'.$k->COLUMN_NAME.'` IS NOT NULL', $keys));
            $orphans[$name] = DB::selectOne('SELECT COUNT(*) AS n FROM `'.$key->TABLE_NAME.'` c LEFT JOIN `'.$key->REFERENCED_TABLE_NAME.'` p ON '.$join.' WHERE '.$notNull.' AND p.`'.$key->REFERENCED_COLUMN_NAME.'` IS NULL')->n;
        }
        $indexes = DB::select('SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_list FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE');
        $result = ['database' => DB::selectOne('SELECT DATABASE() AS name')->name, 'engines' => DB::select('SELECT ENGINE, COUNT(*) AS tables_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() GROUP BY ENGINE'),
            'foreign_key_constraints' => count($groups), 'orphan_counts' => $orphans, 'indexes' => $indexes,
            'tenant_mismatches' => DB::table('appointments as a')->join('patients as p', 'a.patient_id', '=', 'p.id')
                ->join('doctors as d', 'a.doctor_id', '=', 'd.id')->join('services as s', 'a.service_id', '=', 's.id')
                ->where(fn ($query) => $query->whereColumn('a.clinic_id', '!=', 'p.clinic_id')->orWhereColumn('a.clinic_id', '!=', 'd.clinic_id')->orWhereColumn('a.clinic_id', '!=', 's.clinic_id'))->count()];
    } elseif ($action === 'reminder-fixture') {
        $fixture = fixture('scheduler');
        $clinic = Clinic::findOrFail($fixture['clinic_id']);
        $period = $clinic->subscriptionPeriods()->firstOrFail();
        $period->update(['ends_at' => now('Asia/Jakarta')->addDay()->endOfDay()->utc()]);
        $result = ['period_id' => $period->id, 'owner_email' => $fixture['users']['owner']['email']];
    } elseif ($action === 'reminder-state') {
        $rows = SubscriptionReminder::where('subscription_period_id', $input['period_id'])->get();
        $result = ['rows' => $rows->count(), 'sent' => $rows->whereNotNull('sent_at')->count(), 'mailed' => $rows->whereNotNull('mailed_at')->count(),
            'failed' => $rows->whereNotNull('failed_at')->count(), 'queued_jobs' => DB::table('jobs')->count()];
    } elseif ($action === 'restore-photo') {
        $restorePath = getenv('AUDIT_RESTORE_STORAGE') ?: getenv('AUDIT_ROOT').'/private-files-restored';
        if (! str_starts_with(str_replace('\\', '/', $restorePath), str_replace('\\', '/', getenv('AUDIT_ROOT')).'/private-files-restored')) {
            throw new RuntimeException('Restore storage must stay inside the disposable audit directory');
        }
        config(['filesystems.disks.local.root' => $restorePath]);
        Storage::forgetDisk('local');
        $result = executeTask(['type' => 'http', 'actor' => $input['actor'], 'path' => '/doctor/visits/'.$input['visit_id'].'/photos/0']);
        $result['database'] = DB::selectOne('SELECT DATABASE() AS name')->name;
        $result['disk_root_is_restored'] = str_contains(config('filesystems.disks.local.root'), 'private-files-restored');
    } elseif ($action === 'lookup-booking') {
        $appointment = Appointment::where('clinic_id', $input['clinic_id'])->findOrFail($input['appointment_id']);
        $visit = $appointment->visit;
        $result = ['appointment_status' => $appointment->status->value, 'queue_id' => $appointment->queue->id,
            'queue_status' => $appointment->queue->status->value, 'visit_id' => $visit?->id,
            'visit_status' => $visit?->status->value, 'record_status' => $visit?->medicalRecord?->status?->value];
    } elseif ($action === 'doku-sandbox') {
        if (config('services.doku.base_url') !== 'https://api-sandbox.doku.com') {
            throw new RuntimeException('Only the DOKU sandbox host is permitted');
        }
        $fixture = json_decode(file_get_contents(getenv('AUDIT_ROOT').'/fixture.json'), true)['main'];
        $clinic = Clinic::findOrFail($fixture['clinic_id']);
        $before = $clinic->subscription()->firstOrFail()->only(['status', 'subscription_plan_id']);
        $result = ['sandbox_only' => true, 'real_payment_performed' => false, 'public_webhook_receiver' => 'BLOCKED'];
        try {
            $price = SubscriptionPrice::whereHas('plan', fn ($query) => $query->where('code', 'team'))->where('duration_months', 1)->firstOrFail();
            $payment = app(DokuPaymentService::class)->createCheckout($clinic, $price, User::findOrFail($fixture['users']['owner']['id']));
            $result['checkout_http'] = 'success';
            $result['payment_status'] = $payment->status->value;
            $result['https_checkout_url_received'] = str_starts_with($payment->payment_url, 'https://');
            try {
                $event = app(DokuPaymentService::class)->reconcile($payment);
                $result['status_reconciliation'] = 'success';
            } catch (Throwable $exception) {
                $result['status_reconciliation'] = 'BLOCKED';
                $result['reconciliation_exception'] = $exception::class;
            }
        } catch (Throwable $exception) {
            $result['checkout_http'] = 'BLOCKED';
            $result['exception'] = $exception::class;
            $result['provider_http_status'] = $exception instanceof RequestException ? $exception->response->status() : null;
        }
        $result['subscription_unchanged'] = $before === $clinic->subscription()->firstOrFail()->only(['status', 'subscription_plan_id']);
    } elseif ($action === 'factory-contract') {
        $limit = (int) DB::selectOne("SELECT CHARACTER_MAXIMUM_LENGTH AS maximum_length FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_plans' AND COLUMN_NAME = 'code'")->maximum_length;
        $result = ['gate' => 'PASS', 'schema_limit' => $limit];
        foreach (range(0, 10000) as $seed) {
            fake()->seed($seed);
            fake()->unique(true);
            $data = SubscriptionPlan::factory()->raw();
            if (strlen($data['code']) > $limit) {
                $result = ['gate' => 'FAIL', 'seed' => $seed, 'code_length' => strlen($data['code']), 'schema_limit' => $limit, 'database_writes' => 0];
                break;
            }
        }
    } elseif ($action === 'counts') {
        $result = [];
        foreach (DB::select('SHOW TABLES') as $row) {
            $table = array_values((array) $row)[0];
            $result[$table] = DB::table($table)->count();
        }
    } else {
        throw new RuntimeException('Unsupported audit action');
    }
    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    file_put_contents(getenv('AUDIT_ROOT').'/last-audit-error.txt', (string) $exception);
    $result = ['exception' => get_class($exception), 'status' => method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500];
    if ($exception instanceof ValidationException) {
        $result['status'] = 422;
        $result['validation_fields'] = array_keys($exception->errors());
    }
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit(1);
}
