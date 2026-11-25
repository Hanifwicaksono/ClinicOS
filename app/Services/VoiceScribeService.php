<?php

namespace App\Services;

use App\Jobs\ProcessVoiceSession;
use App\MedicalRecordStatus;
use App\Models\AiReviewLog;
use App\Models\Clinic;
use App\Models\User;
use App\Models\Visit;
use App\Models\VoiceSession;
use App\VisitStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class VoiceScribeService
{
    public function __construct(private AuditLogger $audit, private VoiceAudioStorage $audio) {}

    public function enabledFor(int $clinicId): bool
    {
        if (! config('voice.enabled') || ! in_array((string) $clinicId, array_map('trim', config('voice.clinic_ids')), true)) {
            return false;
        }
        if (config('voice.provider') === 'fake') {
            return app()->environment(['local', 'testing']);
        }

        return config('voice.provider') === 'openai'
            && filled(config('voice.api_key')) && filled(config('voice.transcription_model')) && filled(config('voice.extraction_model'))
            && (app()->environment(['local', 'testing']) || config('voice.privacy_approved'));
    }

    public function authorize(Visit $visit, User $actor, ?VoiceSession $session = null, bool $write = true): void
    {
        $actor = $actor->fresh();
        abort_unless($actor?->is_active && $actor->hasRole('Doctor') && $actor->clinic_id === $visit->clinic_id
            && $visit->doctor->is_active && $visit->doctor->clinic_id === $visit->clinic_id
            && $visit->doctor->user_id === $actor->id && $actor->can($write ? 'medical_record.update' : 'medical_record.view'), 403);
        abort_unless($visit->patient()->withoutGlobalScopes()->where('clinic_id', $visit->clinic_id)->exists(), 403);
        abort_unless($visit->medicalRecord !== null && $visit->medicalRecord->patient_id === $visit->patient_id
            && $visit->medicalRecord->doctor_id === $visit->doctor_id, 403);
        if ($session !== null) {
            abort_unless($session->clinic_id === $visit->clinic_id && $session->visit_id === $visit->id
                && $session->patient_id === $visit->patient_id && $session->doctor_id === $visit->doctor_id
                && $session->created_by_user_id === $actor->id, 404);
        }
    }

    public function assertActive(Visit $visit): void
    {
        abort_unless($visit->status === VisitStatus::InProgress && $visit->medicalRecord?->status === MedicalRecordStatus::Draft, 409,
            'Kunjungan tidak aktif atau RME sudah final. Gunakan alur pemeriksaan/koreksi yang tersedia.');
    }

    /** @param array<string, mixed> $data */
    public function start(Visit $visit, User $actor, array $data): VoiceSession
    {
        abort_unless($this->enabledFor($visit->clinic_id), 403, 'ClinicOS Voice belum diaktifkan untuk klinik ini.');

        return DB::transaction(function () use ($visit, $actor, $data): VoiceSession {
            Clinic::query()->lockForUpdate()->findOrFail($visit->clinic_id);
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->authorize($visit, $actor);
            $this->assertActive($visit);
            $existing = VoiceSession::query()->where('clinic_id', $visit->clinic_id)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                $this->authorize($visit, $actor, $existing);

                return $existing;
            }
            abort_if(VoiceSession::query()->where('visit_id', $visit->id)->whereNotIn('status', ['completed', 'cancelled'])->exists(), 409,
                'Sesi sebelumnya belum selesai. Lanjutkan atau batalkan sesi tersebut.');
            abort_if($this->monthlyUsage($visit->clinic_id) >= config('voice.monthly_seconds'), 429, 'Kuota Voice klinik bulan ini habis; RME manual tetap tersedia.');
            $session = VoiceSession::query()->create([
                'clinic_id' => $visit->clinic_id, 'visit_id' => $visit->id, 'patient_id' => $visit->patient_id,
                'doctor_id' => $visit->doctor_id, 'created_by_user_id' => $actor->id,
                'idempotency_key' => $data['idempotency_key'], 'status' => 'recording',
                'consent_status' => 'granted', 'consent_version' => config('voice.consent_version'), 'consented_at' => now(),
                'started_at' => now(), 'provider' => config('voice.provider'),
                'transcription_model' => config('voice.provider') === 'fake' ? 'simulation' : config('voice.transcription_model'),
                'extraction_model' => config('voice.provider') === 'fake' ? 'simulation' : config('voice.extraction_model'),
            ]);
            $this->audit->recordForClinic($visit->clinic, 'voice.consent_granted', $visit, ['visit_id' => $visit->id, 'voice_session_id' => $session->id, 'source' => $session->consent_version], $actor);

            return $session;
        }, 3);
    }

    public function upload(Visit $visit, VoiceSession $session, User $actor, UploadedFile $file, int $number): void
    {
        abort_unless($this->enabledFor($visit->clinic_id), 403);
        $info = $this->audio->inspect($file);
        $path = null;
        try {
            DB::transaction(function () use ($visit, $session, $actor, $file, $number, $info, &$path): void {
                Clinic::query()->lockForUpdate()->findOrFail($visit->clinic_id);
                $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
                $session = VoiceSession::query()->where('clinic_id', $visit->clinic_id)->lockForUpdate()->findOrFail($session->id);
                $this->authorize($visit, $actor, $session);
                $this->assertActive($visit);
                $this->assertSessionProvider($session);
                abort_unless($this->enabledFor($visit->clinic_id) && $session->consent_status === 'granted', 403);
                $existing = $session->segments()->where('segment_no', $number)->first();
                if ($existing !== null) {
                    abort_unless(hash_equals($existing->checksum, $info['checksum']), 409, 'Nomor segmen sudah dipakai audio berbeda.');

                    return;
                }
                abort_unless(in_array($session->status, ['recording', 'paused', 'uploading'], true), 409, 'Sesi tidak menerima audio baru.');
                abort_unless($number === $session->segments()->count() + 1, 409, 'Segmen harus dikirim sesuai urutan.');
                $usage = $this->monthlyUsage($visit->clinic_id);
                abort_if($usage + $info['seconds'] > config('voice.monthly_seconds'), 429, 'Kuota Voice klinik habis; simpan catatan melalui RME manual.');
                abort_if($session->total_seconds + $info['seconds'] > config('voice.max_session_seconds'), 422, 'Rekaman melebihi batas durasi sesi.');
                $path = $this->audio->store($session, $file);
                $session->segments()->create([
                    'segment_no' => $number, 'audio_path' => $path, 'audio_format' => $info['format'],
                    'checksum' => $info['checksum'], 'duration_seconds' => $info['seconds'],
                ]);
                $session->increment('total_seconds', $info['seconds']);
            }, 1);
        } catch (Throwable $error) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
    }

    /** @param array<string, mixed> $data */
    public function change(Visit $visit, VoiceSession $session, User $actor, string $action, array $data): VoiceSession
    {
        return DB::transaction(function () use ($visit, $session, $actor, $action, $data): VoiceSession {
            Clinic::query()->lockForUpdate()->findOrFail($visit->clinic_id);
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $session = VoiceSession::query()->where('clinic_id', $visit->clinic_id)->lockForUpdate()->findOrFail($session->id);
            $this->authorize($visit, $actor, $session);
            if (in_array($action, ['cancel', 'withdraw'], true)) {
                if ($session->status === 'completed' && $action === 'cancel') {
                    abort(409, 'Hasil telah diterapkan. Gunakan pencabutan persetujuan untuk menghapus materi Voice sementara.');
                }
                $session->status = $session->status === 'completed' ? 'completed' : 'cancelled';
                $session->ended_at ??= now();
                if ($action === 'withdraw') {
                    $session->consent_status = 'withdrawn';
                    $session->consent_withdrawn_at = now();
                }
                $session->save();
                $session->transcripts()->get()->each->update(['transcript_text' => null]);
                $session->draft?->update(['payload' => null, 'edited_soap' => null]);
                if ($session->draft !== null) {
                    AiReviewLog::query()->where('draft_id', $session->draft->id)->update(['changes' => null]);
                }
                DB::afterCommit(fn () => $this->purgeAudio($session));
            } else {
                $this->assertActive($visit);
                $this->assertSessionProvider($session);
                abort_unless($this->enabledFor($visit->clinic_id) && $session->consent_status === 'granted', 403);
                if ($action === 'pause' || $action === 'resume') {
                    $target = $action === 'pause' ? 'paused' : 'recording';
                    abort_unless(in_array($session->status, [$target, $action === 'pause' ? 'recording' : 'paused'], true), 409);
                    $session->update(['status' => $target]);
                } elseif ($action === 'stop') {
                    if ($session->status === 'processing') {
                        return $session;
                    }
                    abort_unless(in_array($session->status, ['recording', 'paused', 'uploading'], true), 409);
                    $expected = (int) $data['expected_segments'];
                    abort_unless($expected > 0 && $session->segments()->count() === $expected && $session->segments()->max('segment_no') === $expected, 409, 'Upload belum lengkap. Coba kirim ulang segmen sebelum selesai.');
                    $session->update(['status' => 'processing', 'expected_segments' => $expected, 'ended_at' => now(), 'failure_code' => null]);
                    ProcessVoiceSession::dispatch($session->id)->onConnection('voice')->onQueue('voice')->afterCommit();
                } elseif ($action === 'retry') {
                    abort_if($session->processing_attempts >= 3, 422, 'Batas tiga percobaan pemrosesan sesi tercapai. Gunakan RME manual.');
                    abort_unless($session->status === 'failed' && $session->expected_segments > 0 && ! $session->segments()->whereNull('audio_path')->exists(), 409, 'Audio sudah tidak tersedia atau sesi belum siap diproses ulang.');
                    $session->update(['status' => 'processing', 'failure_code' => null]);
                    ProcessVoiceSession::dispatch($session->id)->onConnection('voice')->onQueue('voice')->afterCommit();
                }
            }
            $this->audit->recordForClinic($visit->clinic, 'voice.'.$action, $visit, ['visit_id' => $visit->id, 'voice_session_id' => $session->id], $actor);

            return $session->refresh();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function review(Visit $visit, VoiceSession $session, User $actor, array $data, bool $approve): VoiceSession
    {
        return DB::transaction(function () use ($visit, $session, $actor, $data, $approve): VoiceSession {
            Clinic::query()->lockForUpdate()->findOrFail($visit->clinic_id);
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $session = VoiceSession::query()->where('clinic_id', $visit->clinic_id)->lockForUpdate()->findOrFail($session->id);
            $this->authorize($visit, $actor, $session);
            $draft = $session->draft()->lockForUpdate()->firstOrFail();
            if ($approve && $session->status === 'completed' && $draft->applied_at !== null) {
                return $session;
            }
            $this->assertActive($visit);
            $this->assertSessionProvider($session);
            abort_unless($this->enabledFor($visit->clinic_id) && $session->consent_status === 'granted' && $session->status === 'review_required' && $draft->payload !== null, 409, 'Draft tidak lagi tersedia untuk disetujui.');
            abort_unless($draft->version === (int) $data['draft_version'], 409, 'Draft telah berubah. Muat hasil terbaru sebelum melanjutkan.');
            $edited = Arr::only($data['soap'], ClinicalDraftValidator::SOAP_FIELDS);
            $before = $draft->edited_soap;
            if ($approve) {
                $fields = Arr::only($edited, $data['fields']);
                $record = $visit->medicalRecord;
                $overwrites = collect($fields)->contains(fn (?string $value, string $key): bool => filled($record->{$key}) && $record->{$key} !== $value);
                abort_if($overwrites && ! filter_var($data['confirm_overwrite'] ?? false, FILTER_VALIDATE_BOOL), 422, 'Konfirmasi penggantian field RME yang sudah terisi.');
                $saved = app(VisitWorkflowService::class)->applyVoiceSoap($visit, $actor, $fields, (int) $data['record_version']);
                $draft->applied_at = now();
                $draft->applied_record_version = $saved->lock_version;
                $draft->review_status = 'approved';
                $session->update(['status' => 'completed', 'approved_at' => now(), 'completed_at' => now()]);
            }
            $draft->edited_soap = $edited;
            $draft->reviewed_by_user_id = $actor->id;
            $draft->version++;
            $draft->save();
            AiReviewLog::query()->create([
                'draft_id' => $draft->id, 'actor_id' => $actor->id, 'action' => $approve ? 'approved' : 'draft_saved',
                'changes' => ['before' => $before, 'after' => $edited, 'fields' => $approve ? $data['fields'] : []],
            ]);
            $this->audit->recordForClinic($visit->clinic, $approve ? 'voice.approved' : 'voice.draft_saved', $visit, ['visit_id' => $visit->id, 'voice_session_id' => $session->id], $actor);

            return $session->refresh();
        }, 3);
    }

    /** @return array<string, mixed> */
    public function present(VoiceSession $session): array
    {
        $draft = $session->draft;
        $transcripts = $session->transcripts()->get()->keyBy('segment_id');

        return [
            'id' => $session->id, 'status' => $session->status, 'consent_status' => $session->consent_status,
            'failure_code' => $session->failure_code, 'total_seconds' => $session->total_seconds,
            'provider' => $session->provider, 'simulation' => $session->provider === 'fake',
            'segments' => $session->segments()->get()->map(fn ($segment): array => [
                'segment_no' => $segment->segment_no, 'status' => $segment->processing_status,
                'text' => $transcripts->get($segment->id)?->transcript_text,
            ])->all(),
            'draft' => $draft === null ? null : [
                'version' => $draft->version, 'payload' => $draft->payload,
                'soap' => $draft->edited_soap ?? collect($draft->payload['soap'] ?? [])->map(fn (array $fact): ?string => $fact['text'])->all(),
                'applied_at' => $draft->applied_at?->toIso8601String(),
            ],
        ];
    }

    public function purgeAudio(VoiceSession $session): void
    {
        foreach ($session->segments()->whereNotNull('audio_path')->get() as $segment) {
            if (Storage::disk('local')->delete($segment->audio_path)) {
                $segment->update(['audio_path' => null, 'purged_at' => now()]);
            }
        }
    }

    private function assertSessionProvider(VoiceSession $session): void
    {
        abort_unless($session->provider === config('voice.provider'), 409,
            'Provider Voice telah berubah. Batalkan sesi lama melalui Opsi sesi, lalu mulai rekaman baru. Hasil lama tidak diubah otomatis.');
    }

    private function monthlyUsage(int $clinicId): int
    {
        return (int) DB::table('voice_segments')->join('voice_sessions', 'voice_sessions.id', '=', 'voice_segments.session_id')
            ->where('voice_sessions.clinic_id', $clinicId)->where('voice_segments.created_at', '>=', now()->startOfMonth())
            ->sum('voice_segments.duration_seconds');
    }
}
