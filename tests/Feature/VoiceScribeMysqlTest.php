<?php

namespace Tests\Feature;

use App\Jobs\ProcessVoiceSession;
use App\Models\AuditLog;
use App\Models\Visit;
use App\Models\VoiceSession;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VoiceScribeMysqlTest extends TestCase
{
    private ?string $databaseName = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('CLINICOS_VOICE_MYSQL_TEST') !== '1') {
            $this->markTestSkipped('Opt-in: uses a newly created disposable MySQL schema, never the application database.');
        }
        $connection = config('database.connections.mysql');
        $connection['url'] = null;
        $connection['database'] = null;
        config(['database.connections.voice_mysql_setup' => $connection]);
        $name = 'clinicos_voice_test_'.bin2hex(random_bytes(6));
        DB::connection('voice_mysql_setup')->statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->databaseName = $name;
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => $name, 'database.connections.mysql.url' => null]);
        DB::purge('mysql');
        Artisan::call('migrate', ['--database' => 'mysql', '--force' => true, '--no-interaction' => true]);
        $this->seed(RoleAndPermissionSeeder::class);
        config(['voice.enabled' => true, 'voice.provider' => 'fake']);
        Storage::fake('local');
        Bus::fake([ProcessVoiceSession::class]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        if ($this->databaseName !== null && preg_match('/^clinicos_voice_test_[a-f0-9]{12}$/D', $this->databaseName)) {
            DB::purge('mysql');
            DB::connection('voice_mysql_setup')->statement('DROP DATABASE `'.$this->databaseName.'`');
            DB::purge('voice_mysql_setup');
        }
        parent::tearDown();
    }

    public function test_voice_lifecycle_processing_and_approval_use_numeric_audit_targets_on_strict_mysql(): void
    {
        $visit = Visit::factory()->withConsistentRelations()->create();
        config(['voice.clinic_ids' => [(string) $visit->clinic_id]]);
        $base = route('doctor.voice.index', $visit);
        $response = $this->actingAs($visit->doctor->user)->postJson($base, [
            'consent' => true, 'patient_confirmed' => true, 'consent_version' => config('voice.consent_version'),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertOk();
        $session = VoiceSession::query()->findOrFail($response->json('id'));
        $url = $base.'/'.$session->id;
        $this->postJson($url.'/pause')->assertOk();
        $this->postJson($url.'/resume')->assertOk();
        $pcm = str_repeat("\0", 32000);
        $wav = 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 16000, 32000, 2, 16).'data'.pack('V', strlen($pcm)).$pcm;
        $this->withHeader('Accept', 'application/json')->post($url.'/segments', [
            'segment_no' => 1, 'audio' => UploadedFile::fake()->createWithContent('voice.wav', $wav),
        ])->assertOk();
        $this->postJson($url.'/stop', ['expected_segments' => 1])->assertOk();
        $this->app->call([new ProcessVoiceSession($session->id), 'handle']);
        $this->assertSame('review_required', $session->refresh()->status);
        $draft = $session->draft()->sole();
        $soap = collect($draft->payload['soap'])->map(fn (array $fact): ?string => $fact['text'])->all();
        $this->postJson($url.'/review', ['draft_version' => $draft->version, 'soap' => $soap])->assertOk();
        $this->postJson($url.'/approve', [
            'draft_version' => $draft->refresh()->version, 'soap' => $soap, 'fields' => ['subjective'],
            'record_version' => $visit->medicalRecord->lock_version, 'review_confirmed' => true,
        ])->assertOk()->assertJsonPath('status', 'completed');
        $logs = AuditLog::query()->where('action', 'like', 'voice.%')->get();
        $this->assertCount(7, $logs);
        foreach ($logs as $log) {
            $this->assertSame($visit->getMorphClass(), $log->entity_type);
            $this->assertSame($visit->id, (int) $log->entity_id);
            $this->assertSame($session->id, $log->metadata['voice_session_id']);
        }
    }
}
