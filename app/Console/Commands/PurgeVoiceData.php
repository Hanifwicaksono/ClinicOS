<?php

namespace App\Console\Commands;

use App\Models\AiClinicalDraft;
use App\Models\AiReviewLog;
use App\Models\VoiceSegment;
use App\Models\VoiceSession;
use App\Models\VoiceTranscript;
use App\Services\VoiceScribeService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class PurgeVoiceData extends Command
{
    protected $signature = 'voice:purge';

    protected $description = 'Hapus materi Voice sementara sesuai retensi tanpa menghapus rekam medis';

    public function handle(VoiceScribeService $scribe): int
    {
        VoiceSession::query()->where('status', 'processing')->where('updated_at', '<', now()->subHours(2))
            ->update(['status' => 'failed', 'failure_code' => 'worker_stalled']);
        VoiceSession::query()->whereIn('status', ['recording', 'paused', 'uploading'])
            ->where('started_at', '<', now()->subHours(max(1, config('voice.audio_retention_hours'))))
            ->update(['status' => 'cancelled', 'ended_at' => now(), 'failure_code' => 'session_abandoned']);
        VoiceSession::query()->where(function (Builder $query): void {
            $query->where('started_at', '<', now()->subHours(max(1, config('voice.audio_retention_hours'))))
                ->orWhere('consent_status', 'withdrawn')->orWhere('status', 'cancelled');
        })->eachById(function (VoiceSession $session) use ($scribe): void {
            $scribe->purgeAudio($session);
        });
        foreach (Storage::disk('local')->allFiles('voice') as $path) {
            if (Storage::disk('local')->lastModified($path) < now()->subHours(max(1, config('voice.audio_retention_hours')))->timestamp
                && ! VoiceSegment::query()->where('audio_path', $path)->exists()) {
                Storage::disk('local')->delete($path);
            }
        }
        VoiceTranscript::query()->where('created_at', '<', now()->subDays(max(1, config('voice.transcript_retention_days'))))
            ->update(['transcript_text' => null]);
        AiClinicalDraft::query()->where('created_at', '<', now()->subDays(max(1, config('voice.draft_retention_days'))))
            ->whereNotNull('payload')->eachById(function (AiClinicalDraft $draft): void {
                $draft->update(['payload' => null, 'edited_soap' => null]);
                VoiceSession::query()->whereKey($draft->session_id)->where('status', 'review_required')
                    ->update(['status' => 'failed', 'failure_code' => 'draft_expired']);
            });
        AiReviewLog::query()->where('created_at', '<', now()->subDays(max(1, config('voice.draft_retention_days'))))->update(['changes' => null]);
        $this->info('Retensi materi Voice selesai. RME dan metadata audit tetap tersimpan.');

        return self::SUCCESS;
    }
}
