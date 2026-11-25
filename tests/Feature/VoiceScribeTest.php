<?php

namespace Tests\Feature;

use App\Jobs\ProcessVoiceSession;
use App\MedicalRecordStatus;
use App\Models\AiReviewLog;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Visit;
use App\Models\VoiceSession;
use App\Services\AuditLogger;
use App\Services\ClinicalExtractionProvider;
use App\Services\FakeScribeProvider;
use App\Services\SubscriptionService;
use App\Services\TranscriptionProvider;
use App\Services\VoiceAudioStorage;
use App\Services\VoiceScribeService;
use App\VisitStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class VoiceScribeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        config(['voice.enabled' => true, 'voice.provider' => 'fake']);
        Storage::fake('local');
        Bus::fake([ProcessVoiceSession::class]);
        Http::preventStrayRequests();
    }

    public function test_switching_to_real_provider_keeps_old_sessions_labelled_and_blocks_applying_dummy_data(): void
    {
        $visit = $this->visit();
        $session = $this->ready($visit);
        $this->runJob($session);
        $payload = [...$this->reviewPayload($session), 'fields' => ['subjective'], 'record_version' => 0, 'review_confirmed' => true];
        config(['voice.provider' => 'openai', 'voice.api_key' => 'test-key', 'voice.transcription_model' => 'configured-stt', 'voice.extraction_model' => 'configured-extractor']);
        $this->getJson($this->url($visit, $session))->assertOk()->assertJsonPath('provider', 'fake')->assertJsonPath('simulation', true);
        $this->postJson($this->url($visit, $session).'/approve', $payload)->assertConflict();
        $this->assertNull($visit->medicalRecord->fresh()->subjective);
        $this->postJson($this->url($visit, $session).'/cancel')->assertOk();
        $this->postJson($this->url($visit), $this->consent())->assertOk()->assertJsonPath('provider', 'openai')->assertJsonPath('simulation', false);
        Http::assertNothingSent();
    }

    public function test_feature_is_disabled_by_default_and_requires_a_clinic_allowlist(): void
    {
        $visit = $this->visit();
        config(['voice.enabled' => false]);
        $this->actingAs($visit->doctor->user)->postJson($this->url($visit), $this->consent())->assertForbidden();
        config(['voice.enabled' => true, 'voice.clinic_ids' => []]);
        $this->postJson($this->url($visit), $this->consent())->assertForbidden();
        $this->assertDatabaseCount('voice_sessions', 0);
    }

    public function test_consent_and_patient_confirmation_are_required_and_start_is_idempotent(): void
    {
        $visit = $this->visit();
        $this->actingAs($visit->doctor->user)->postJson($this->url($visit), [])->assertUnprocessable();
        $this->postJson($this->url($visit), [...$this->consent(), 'consent' => false])->assertUnprocessable();
        $this->postJson($this->url($visit), [...$this->consent(), 'consent_version' => 'old'])->assertUnprocessable();
        $data = $this->consent();
        $first = $this->postJson($this->url($visit), $data)->assertOk()->assertJsonPath('status', 'recording');
        $this->postJson($this->url($visit), $data)->assertOk()->assertJsonPath('id', $first->json('id'));
        $this->assertDatabaseCount('voice_sessions', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'voice.consent_granted']);
        $audit = AuditLog::query()->where('action', 'voice.consent_granted')->sole();
        $this->assertSame($visit->getMorphClass(), $audit->entity_type);
        $this->assertSame($visit->id, (int) $audit->entity_id);
        $this->assertSame($first->json('id'), $audit->metadata['voice_session_id']);
        $this->postJson($this->url($visit), $this->consent())->assertConflict();
    }

    public function test_database_errors_return_safe_messages_even_with_debug_enabled_and_roll_back_start(): void
    {
        $visit = $this->visit();
        config(['app.debug' => true]);
        $this->mock(AuditLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('recordForClinic')->once()->andThrow(new QueryException(
                'mysql', 'insert into audit_logs (entity_id) values (?)', ['private-test-value'], new \PDOException('simulated database failure'),
            ));
        });
        Log::shouldReceive('error')->once()->with('voice_database_failed', ['route' => 'doctor.voice.store']);
        $response = $this->actingAs($visit->doctor->user)->postJson($this->url($visit), $this->consent())->assertStatus(500);
        $response->assertExactJson(['message' => 'ClinicOS Voice sedang mengalami gangguan. Coba lagi atau gunakan RME manual.']);
        $this->assertStringNotContainsString('audit_logs', $response->getContent());
        $this->assertStringNotContainsString('private-test-value', $response->getContent());
        $this->assertDatabaseCount('voice_sessions', 0);
    }

    public function test_non_doctors_other_doctors_and_other_tenants_cannot_access_sessions(): void
    {
        $visit = $this->visit();
        $session = $this->createVoiceSession($visit);
        $other = $this->visit();
        $this->actingAs($other->doctor->user)->getJson($this->url($visit, $session))->assertForbidden();
        $this->postJson($this->url($other, $session).'/pause')->assertNotFound();
        $staff = User::factory()->forClinic($visit->clinic)->create();
        $staff->assignRole('Clinic Admin');
        $this->actingAs($staff)->getJson($this->url($visit, $session))->assertForbidden();
        $this->postJson($this->url($visit), $this->consent())->assertForbidden();
        $doctor = User::factory()->forClinic($visit->clinic)->create();
        $doctor->assignRole('Doctor');
        $this->actingAs($doctor)->getJson($this->url($visit, $session))->assertForbidden();
    }

    public function test_pause_resume_stop_and_segment_completeness(): void
    {
        $visit = $this->visit();
        $session = $this->createVoiceSession($visit);
        $this->postJson($this->url($visit, $session).'/pause')->assertOk()->assertJsonPath('status', 'paused');
        $this->postJson($this->url($visit, $session).'/resume')->assertOk()->assertJsonPath('status', 'recording');
        $this->postJson($this->url($visit, $session).'/stop', ['expected_segments' => 1])->assertConflict();
        $this->upload($visit, $session)->assertOk();
        $this->postJson($this->url($visit, $session).'/stop', ['expected_segments' => 2])->assertConflict();
        $this->postJson($this->url($visit, $session).'/stop', ['expected_segments' => 1])->assertOk()->assertJsonPath('status', 'processing');
        Bus::assertDispatched(ProcessVoiceSession::class, fn ($job): bool => $job->sessionId === $session->id && $job->connection === 'voice');
        $this->postJson($this->url($visit, $session).'/pause')->assertConflict();
    }

    public function test_upload_checks_signature_duration_order_idempotency_and_encryption(): void
    {
        $visit = $this->visit();
        $session = $this->createVoiceSession($visit);
        $this->upload($visit, $session, number: 2)->assertConflict();
        $this->withHeader('Accept', 'application/json')->post($this->url($visit, $session).'/segments', [
            'segment_no' => 1, 'audio' => UploadedFile::fake()->createWithContent('fake.webm', 'not audio'),
        ])->assertUnprocessable();
        $this->upload($visit, $session, seconds: 66)->assertUnprocessable();
        $this->upload($visit, $session)->assertOk();
        $this->upload($visit, $session)->assertOk();
        $this->upload($visit, $session, seconds: 2)->assertConflict();
        $this->assertSame(1, $session->segments()->count());
        $this->assertSame(1, $session->refresh()->total_seconds);
        $segment = $session->segments()->sole();
        $this->assertStringStartsWith('voice/'.$visit->clinic_id.'/', $segment->audio_path);
        $this->assertNotSame($this->wav(), Storage::disk('local')->get($segment->audio_path));
        $this->assertSame($this->wav(), app(VoiceAudioStorage::class)->read($segment->audio_path));
    }

    public function test_quota_and_session_duration_are_checked_on_server(): void
    {
        $visit = $this->visit();
        $session = $this->createVoiceSession($visit);
        $session->forceFill(['created_at' => now()->subMonth()])->save();
        config(['voice.monthly_seconds' => 1]);
        $this->upload($visit, $session)->assertOk();
        $this->upload($visit, $session, number: 2)->assertStatus(429);
        config(['voice.monthly_seconds' => 100, 'voice.max_session_seconds' => 1]);
        $this->upload($visit, $session, number: 2)->assertUnprocessable();
        $this->assertSame(1, $session->segments()->count());
    }

    public function test_job_creates_encrypted_transcripts_and_soap_without_changing_rme_or_billing(): void
    {
        $visit = $this->visit();
        $session = $this->ready($visit);
        $before = $visit->medicalRecord->fresh()->toArray();
        $this->runJob($session);
        $this->assertSame('review_required', $session->refresh()->status);
        $this->assertSame($before, $visit->medicalRecord->fresh()->toArray());
        $this->assertDatabaseCount('patient_bills', 0);
        $this->assertSame(0, $visit->medicalRecord->prescription()->count());
        $transcript = $session->transcripts()->sole();
        $this->assertStringNotContainsString('Pasien mengatakan', $transcript->getRawOriginal('transcript_text'));
        $draft = $session->draft()->sole();
        $this->assertNull($draft->payload['soap']['assessment']['text']);
        $this->assertStringNotContainsString('Demam tiga hari', $draft->getRawOriginal('payload'));
        $this->getJson($this->url($visit, $session))->assertOk()->assertJsonPath('simulation', true)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->runJob($session);
        $this->assertDatabaseCount('ai_clinical_drafts', 1);
    }

    public function test_review_editing_uses_version_and_keeps_medical_content_out_of_general_audit(): void
    {
        $visit = $this->visit();
        $session = $this->ready($visit);
        $this->runJob($session);
        $data = $this->reviewPayload($session);
        $data['soap']['subjective'] = 'Catatan revisi dokter.';
        $this->postJson($this->url($visit, $session).'/review', $data)->assertOk()->assertJsonPath('draft.version', 2);
        $this->postJson($this->url($visit, $session).'/review', $data)->assertConflict();
        $this->assertSame('Catatan revisi dokter.', $session->draft()->sole()->edited_soap['subjective']);
        $this->assertNull($visit->medicalRecord->fresh()->subjective);
        $this->assertStringNotContainsString('Catatan revisi dokter.', json_encode(AuditLog::all()->toArray()));
        $this->assertSame('Catatan revisi dokter.', AiReviewLog::sole()->changes['after']['subjective']);
    }

    public function test_approval_only_patches_selected_soap_preserves_existing_data_and_is_idempotent(): void
    {
        $visit = $this->visit();
        $record = $visit->medicalRecord;
        $record->update(['objective' => 'Temuan manual', 'photos' => [['path' => 'existing.jpg', 'name' => 'existing.jpg']]]);
        $record->vitalSign()->create(['temperature_c' => 37.1]);
        $prescription = $record->prescription()->create(['notes' => 'Resep manual']);
        $prescription->items()->create(['medicine_name' => 'Obat manual', 'dosage' => '1', 'frequency' => '1', 'quantity' => 1]);
        $record->diagnoses()->create(['name' => 'Diagnosis manual', 'is_primary' => true]);
        $record->treatments()->create(['name' => 'Tindakan manual', 'quantity' => 1]);
        $session = $this->ready($visit);
        $this->runJob($session);
        $data = [...$this->reviewPayload($session), 'fields' => ['subjective'], 'record_version' => 0, 'review_confirmed' => true];
        $this->postJson($this->url($visit, $session).'/approve', $data)->assertOk()->assertJsonPath('status', 'completed');
        $this->postJson($this->url($visit, $session).'/approve', $data)->assertOk();
        $record->refresh();
        $this->assertSame(1, $record->lock_version);
        $this->assertSame('Demam tiga hari.', $record->subjective);
        $this->assertSame('Temuan manual', $record->objective);
        $this->assertSame(MedicalRecordStatus::Draft, $record->status);
        $this->assertSame(1, $record->vitalSign()->count());
        $this->assertSame(1, $record->prescription()->count());
        $this->assertSame(1, $record->diagnoses()->count());
        $this->assertSame(1, $record->treatments()->count());
        $this->assertCount(1, $record->photos);
        $this->assertDatabaseCount('patient_bills', 0);
        $this->assertSame(1, AiReviewLog::query()->where('action', 'approved')->count());
    }

    public function test_approval_requires_explicit_review_overwrite_confirmation_and_current_record_version(): void
    {
        $visit = $this->visit();
        $session = $this->ready($visit);
        $this->runJob($session);
        $data = [...$this->reviewPayload($session), 'fields' => ['subjective'], 'record_version' => 0];
        $this->postJson($this->url($visit, $session).'/approve', $data)->assertUnprocessable();
        $data['review_confirmed'] = true;
        $visit->medicalRecord->update(['subjective' => 'Manual']);
        $this->postJson($this->url($visit, $session).'/approve', $data)->assertUnprocessable();
        $data['confirm_overwrite'] = true;
        $visit->medicalRecord->forceFill(['lock_version' => 1])->save();
        $this->postJson($this->url($visit, $session).'/approve', $data)->assertConflict();
        $this->assertSame('Manual', $visit->medicalRecord->fresh()->subjective);
        $data['record_version'] = 1;
        $this->postJson($this->url($visit, $session).'/approve', $data)->assertOk();
    }

    public function test_finalized_or_paused_visit_and_revoked_permission_block_approval(): void
    {
        $visit = $this->visit();
        $session = $this->ready($visit);
        $this->runJob($session);
        $data = [...$this->reviewPayload($session), 'fields' => ['subjective'], 'record_version' => 0, 'review_confirmed' => true];
        $visit->update(['status' => VisitStatus::Paused]);
        $this->postJson($this->url($visit, $session).'/approve', $data)->assertConflict();
        $visit->update(['status' => VisitStatus::Completed]);
        $visit->medicalRecord->update(['status' => MedicalRecordStatus::Final]);
        $this->postJson($this->url($visit, $session).'/approve', $data)->assertConflict();
        $visit->doctor->user->removeRole('Doctor');
        $this->postJson($this->url($visit, $session).'/approve', $data)->assertForbidden();
    }

    public function test_job_rechecks_permission_and_finalization_before_sending_audio(): void
    {
        $visit = $this->visit();
        $session = $this->ready($visit);
        $visit->update(['status' => VisitStatus::Completed]);
        $visit->medicalRecord->update(['status' => MedicalRecordStatus::Final]);
        $this->runJob($session);
        $this->assertSame('failed', $session->refresh()->status);
        $this->assertDatabaseCount('voice_transcripts', 0);
        Http::assertNothingSent();
    }

    public function test_withdrawal_cancels_processing_removes_temporary_data_and_preserves_rme(): void
    {
        $visit = $this->visit();
        $session = $this->ready($visit);
        $path = $session->segments()->sole()->audio_path;
        $this->postJson($this->url($visit, $session).'/withdraw')->assertOk()->assertJsonPath('consent_status', 'withdrawn');
        $this->runJob($session);
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseCount('ai_clinical_drafts', 0);
        $this->assertNotNull($visit->medicalRecord->fresh());
    }

    public function test_cancellation_during_extraction_discards_late_provider_output(): void
    {
        $visit = $this->visit();
        $session = $this->ready($visit);
        $fake = new FakeScribeProvider;
        $extractor = Mockery::mock(ClinicalExtractionProvider::class);
        $extractor->shouldReceive('extract')->once()->andReturnUsing(function ($transcripts, $model) use ($visit, $session, $fake): array {
            app(VoiceScribeService::class)->change($visit, $session, $visit->doctor->user, 'withdraw', []);

            return $fake->extract($transcripts, $model);
        });
        $this->app->instance(ClinicalExtractionProvider::class, $extractor);
        $this->runJob($session);
        $this->assertSame('cancelled', $session->refresh()->status);
        $this->assertDatabaseCount('ai_clinical_drafts', 0);
        $this->assertNull($session->transcripts()->sole()->transcript_text);
    }

    public function test_retention_removes_audio_transcript_and_draft_without_removing_final_rme(): void
    {
        $visit = $this->visit();
        $session = $this->ready($visit);
        $this->runJob($session);
        $path = $session->segments()->sole()->audio_path;
        $visit->medicalRecord->update(['status' => MedicalRecordStatus::Final, 'subjective' => 'Catatan resmi']);
        $this->travel(31)->days();
        $this->artisan('voice:purge')->assertExitCode(0);
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($session->transcripts()->sole()->transcript_text);
        $this->assertNull($session->draft()->sole()->payload);
        $this->assertSame('Catatan resmi', $visit->medicalRecord->fresh()->subjective);
        $this->assertSame(MedicalRecordStatus::Final, $visit->medicalRecord->fresh()->status);
    }

    public function test_panel_and_microphone_policy_only_appear_for_enabled_authorized_visit(): void
    {
        $visit = $this->visit();
        $this->actingAs($visit->doctor->user)->get(route('doctor.visits.show', $visit))
            ->assertOk()->assertSee('data-voice-panel', false)->assertSeeText('Isi kolom SOAP')
            ->assertDontSee('data-voice-field=', false)->assertDontSee('Simpan Draft Voice')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=(self)');
        config(['voice.enabled' => false]);
        $this->get(route('doctor.visits.show', $visit))->assertOk()->assertDontSee('data-voice-panel', false)
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
    }

    public function test_transient_extraction_failure_retries_without_transcribing_or_charging_quota_twice(): void
    {
        $visit = $this->visit();
        $session = $this->ready($visit);
        $extractor = Mockery::mock(ClinicalExtractionProvider::class);
        $extractor->shouldReceive('extract')->once()->andThrow(new \RuntimeException('sensitive-provider-message'));
        $this->app->instance(ClinicalExtractionProvider::class, $extractor);
        try {
            $this->runJob($session);
            $this->fail('Transient provider error must trigger queue retry.');
        } catch (\RuntimeException $error) {
            $this->assertSame('voice_processing_retry', $error->getMessage());
        }
        $this->assertSame('processing', $session->refresh()->status);
        $this->assertSame(1, $session->transcripts()->count());
        $this->app->instance(ClinicalExtractionProvider::class, new FakeScribeProvider);
        $transcriber = Mockery::mock(TranscriptionProvider::class);
        $transcriber->shouldNotReceive('transcribe');
        $this->app->instance(TranscriptionProvider::class, $transcriber);
        $this->runJob($session);
        $this->assertSame('review_required', $session->refresh()->status);
        $this->assertSame(1, $session->total_seconds);
        $this->assertSame(1, $session->transcripts()->count());
    }

    public function test_exhausted_credit_stops_automatic_retries_without_changing_the_medical_record(): void
    {
        $visit = $this->visit();
        $session = $this->ready($visit);
        $transcriber = Mockery::mock(TranscriptionProvider::class);
        $transcriber->shouldReceive('transcribe')->once()->andThrow(new \RuntimeException('voice_credit_balance_exhausted'));
        $this->app->instance(TranscriptionProvider::class, $transcriber);
        $this->runJob($session);
        $this->assertSame('failed', $session->refresh()->status);
        $this->assertSame('voice_credit_balance_exhausted', $session->failure_code);
        $this->assertSame(1, $session->processing_attempts);
        $this->assertDatabaseCount('voice_transcripts', 0);
        $this->assertDatabaseCount('ai_clinical_drafts', 0);
        $this->assertNull($visit->medicalRecord->fresh()->subjective);
        $this->getJson($this->url($visit, $session))->assertOk()->assertJsonPath('failure_code', 'voice_credit_balance_exhausted');
    }

    public function test_invalid_clinical_output_and_worker_failures_are_visible_and_retryable(): void
    {
        $visit = $this->visit();
        $session = $this->ready($visit);
        $extractor = Mockery::mock(ClinicalExtractionProvider::class);
        $extractor->shouldReceive('extract')->once()->andReturn(['soap' => []]);
        $this->app->instance(ClinicalExtractionProvider::class, $extractor);
        $this->runJob($session);
        $this->assertSame('invalid_clinical_output', $session->refresh()->failure_code);
        $this->assertSame('failed', $session->status);
        $this->assertDatabaseCount('ai_clinical_drafts', 0);
        $this->postJson($this->url($visit, $session).'/retry')->assertOk()->assertJsonPath('status', 'processing');
        (new ProcessVoiceSession($session->id))->failed(new \RuntimeException('sensitive failure'));
        $this->assertSame('worker_failed', $session->refresh()->failure_code);
        app(VoiceScribeService::class)->purgeAudio($session);
        $this->postJson($this->url($visit, $session).'/retry')->assertConflict();
    }

    public function test_manual_retries_cannot_exceed_the_session_processing_limit(): void
    {
        $visit = $this->visit();
        $session = $this->ready($visit);
        $extractor = Mockery::mock(ClinicalExtractionProvider::class);
        $extractor->shouldReceive('extract')->times(3)->andReturn(['invalid' => true]);
        $this->app->instance(ClinicalExtractionProvider::class, $extractor);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->runJob($session);
            if ($attempt < 2) {
                $this->postJson($this->url($visit, $session).'/retry')->assertOk();
            }
        }
        $this->postJson($this->url($visit, $session).'/retry')->assertUnprocessable();
        $this->assertSame(3, (int) $session->refresh()->processing_attempts);
    }

    public function test_withdrawal_remains_available_for_closed_visits_when_subscription_is_read_only(): void
    {
        $visit = $this->visit();
        $session = $this->createVoiceSession($visit);
        $visit->update(['status' => VisitStatus::Completed]);
        $visit->medicalRecord->update(['status' => MedicalRecordStatus::Final]);
        $this->mock(SubscriptionService::class, function ($mock): void {
            $mock->shouldReceive('allowsOperationalWrites')->andReturn(false);
        });
        $this->postJson($this->url($visit, $session).'/withdraw')->assertOk()->assertJsonPath('consent_status', 'withdrawn');
        $this->postJson($this->url($visit), $this->consent())->assertForbidden();
    }

    private function visit(): Visit
    {
        $visit = Visit::factory()->withConsistentRelations()->create();
        config(['voice.clinic_ids' => array_merge(config('voice.clinic_ids'), [(string) $visit->clinic_id])]);

        return $visit;
    }

    /** @return array<string, mixed> */
    private function consent(): array
    {
        return ['consent' => true, 'patient_confirmed' => true, 'consent_version' => config('voice.consent_version'), 'idempotency_key' => (string) Str::uuid()];
    }

    private function createVoiceSession(Visit $visit): VoiceSession
    {
        $response = $this->actingAs($visit->doctor->user)->postJson($this->url($visit), $this->consent())->assertOk();

        return VoiceSession::query()->findOrFail($response->json('id'));
    }

    private function ready(Visit $visit): VoiceSession
    {
        $session = $this->createVoiceSession($visit);
        $this->upload($visit, $session)->assertOk();
        $this->postJson($this->url($visit, $session).'/stop', ['expected_segments' => 1])->assertOk();

        return $session->refresh();
    }

    private function runJob(VoiceSession $session): void
    {
        $this->app->call([new ProcessVoiceSession($session->id), 'handle']);
    }

    private function url(Visit $visit, ?VoiceSession $session = null): string
    {
        return route('doctor.voice.index', $visit).($session === null ? '' : '/'.$session->id);
    }

    private function upload(Visit $visit, VoiceSession $session, int $number = 1, int $seconds = 1): TestResponse
    {
        return $this->withHeader('Accept', 'application/json')->post($this->url($visit, $session).'/segments', [
            'segment_no' => $number, 'audio' => UploadedFile::fake()->createWithContent('voice.wav', $this->wav($seconds)),
        ]);
    }

    private function wav(int $seconds = 1): string
    {
        $pcm = str_repeat("\0", 16000 * 2 * $seconds);

        return 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 16000, 32000, 2, 16).'data'.pack('V', strlen($pcm)).$pcm;
    }

    /** @return array<string, mixed> */
    private function reviewPayload(VoiceSession $session): array
    {
        $draft = $session->draft()->sole();

        return ['draft_version' => $draft->version, 'soap' => collect($draft->payload['soap'])->map(fn (array $fact): ?string => $fact['text'])->all()];
    }
}
