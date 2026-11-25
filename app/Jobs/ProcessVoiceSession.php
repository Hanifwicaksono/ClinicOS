<?php

namespace App\Jobs;

use App\Models\AiClinicalDraft;
use App\Models\Clinic;
use App\Models\User;
use App\Models\Visit;
use App\Models\VoiceSession;
use App\Services\AuditLogger;
use App\Services\ClinicalDraftValidator;
use App\Services\ClinicalExtractionProvider;
use App\Services\TranscriptionProvider;
use App\Services\VoiceAudioStorage;
use App\Services\VoiceScribeService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ProcessVoiceSession implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1200;

    public int $uniqueFor = 1260;

    public bool $failOnTimeout = true;

    public function __construct(public string $sessionId) {}

    public function uniqueId(): string
    {
        return $this->sessionId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(VoiceScribeService $scribe, VoiceAudioStorage $audio, TranscriptionProvider $transcriber, ClinicalExtractionProvider $extractor, ClinicalDraftValidator $validator): void
    {
        try {
            $session = $this->context($scribe);
            if ($session === null) {
                return;
            }
            if (! VoiceSession::query()->whereKey($session->id)->where('status', 'processing')->where('processing_attempts', '<', 3)->increment('processing_attempts')) {
                VoiceSession::query()->whereKey($session->id)->where('status', 'processing')->update(['status' => 'failed', 'failure_code' => 'processing_limit']);

                return;
            }
            $segments = $session->segments()->get();
            if ($segments->count() !== $session->expected_segments) {
                throw new RuntimeException('voice_audio_missing');
            }
            foreach ($segments as $segment) {
                if ($this->context($scribe) === null) {
                    return;
                }
                if ($session->transcripts()->where('segment_id', $segment->id)->whereNotNull('transcript_text')->exists()) {
                    continue;
                }
                if ($segment->audio_path === null) {
                    throw new RuntimeException('voice_audio_missing');
                }
                $text = $transcriber->transcribe($audio->read($segment->audio_path), $segment->audio_format, $session->transcription_model);
                if (blank($text) || mb_strlen($text) > 20000) {
                    throw new RuntimeException('voice_transcription_failed');
                }
                $this->publish($scribe, function (VoiceSession $locked) use ($segment, $text): void {
                    $locked->transcripts()->updateOrCreate(['segment_id' => $segment->id], ['transcript_text' => $text]);
                    $segment->update(['processing_status' => 'transcribed']);
                });
            }
            $session = $this->context($scribe);
            if ($session === null) {
                return;
            }
            $transcripts = [];
            $bySegment = $session->transcripts()->get()->keyBy('segment_id');
            foreach ($segments as $segment) {
                $text = $bySegment->get($segment->id)?->transcript_text;
                if (! is_string($text) || blank($text)) {
                    throw new RuntimeException('voice_transcription_failed');
                }
                $transcripts[$segment->segment_no] = $text;
            }
            if (mb_strlen(implode(' ', $transcripts)) > 100000) {
                throw new RuntimeException('voice_transcript_too_long');
            }
            $payload = $validator->validate($extractor->extract($transcripts, $session->extraction_model), $transcripts);
            $this->publish($scribe, function (VoiceSession $locked) use ($payload): void {
                AiClinicalDraft::query()->firstOrCreate(['session_id' => $locked->id], ['payload' => $payload]);
                $locked->update(['status' => 'review_required', 'failure_code' => null]);
                app(AuditLogger::class)->recordForClinic($locked->clinic, 'voice.draft_created', $locked->visit, ['visit_id' => $locked->visit_id, 'voice_session_id' => $locked->id], User::query()->find($locked->created_by_user_id));
            });
        } catch (Throwable $error) {
            $code = match (true) {
                $error instanceof ValidationException => 'invalid_clinical_output',
                $error instanceof HttpException => 'access_or_visit_changed',
                in_array($error->getMessage(), ['voice_audio_missing', 'voice_transcript_too_long', 'voice_credit_balance_exhausted'], true) => $error->getMessage(),
                default => 'provider_unavailable',
            };
            $terminal = $code !== 'provider_unavailable' || $this->attempts() >= $this->tries
                || VoiceSession::query()->whereKey($this->sessionId)->value('processing_attempts') >= 3;
            VoiceSession::query()->whereKey($this->sessionId)->where('status', 'processing')
                ->update(['failure_code' => $code, 'status' => $terminal ? 'failed' : 'processing']);
            if (! $terminal) {
                throw new RuntimeException('voice_processing_retry');
            }
        }
    }

    private function context(VoiceScribeService $scribe): ?VoiceSession
    {
        $session = VoiceSession::query()->find($this->sessionId);
        if ($session === null || $session->status !== 'processing' || $session->consent_status !== 'granted') {
            return null;
        }
        $actor = User::query()->find($session->created_by_user_id);
        abort_unless($actor !== null && $scribe->enabledFor($session->clinic_id) && $session->provider === config('voice.provider'), 403);
        $visit = Visit::query()->where('clinic_id', $session->clinic_id)->findOrFail($session->visit_id);
        $scribe->authorize($visit, $actor, $session);
        $scribe->assertActive($visit);

        return $session;
    }

    private function publish(VoiceScribeService $scribe, callable $callback): void
    {
        DB::transaction(function () use ($scribe, $callback): void {
            $session = VoiceSession::query()->findOrFail($this->sessionId);
            Clinic::query()->lockForUpdate()->findOrFail($session->clinic_id);
            Visit::query()->where('clinic_id', $session->clinic_id)->lockForUpdate()->findOrFail($session->visit_id);
            VoiceSession::query()->where('clinic_id', $session->clinic_id)->lockForUpdate()->findOrFail($session->id);
            $session = $this->context($scribe);
            if ($session !== null) {
                $callback($session);
            }
        }, 3);
    }

    public function failed(?Throwable $exception): void
    {
        VoiceSession::query()->whereKey($this->sessionId)->where('status', 'processing')
            ->update(['status' => 'failed', 'failure_code' => 'worker_failed']);
    }
}
