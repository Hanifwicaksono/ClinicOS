<?php

namespace App\Services;

use App\Models\VoiceSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class VoiceAudioStorage
{
    /** @return array{format:string, seconds:int, checksum:string} */
    public function inspect(UploadedFile $file): array
    {
        if (! $file->isValid() || $file->getSize() <= 0 || $file->getSize() > config('voice.max_segment_kb') * 1024) {
            $this->reject();
        }
        $signature = file_get_contents($file->getRealPath(), false, null, 0, 16);
        if ($signature === false) {
            $this->reject();
        }
        $format = match (true) {
            str_starts_with($signature, "\x1a\x45\xdf\xa3") => 'webm',
            substr($signature, 4, 4) === 'ftyp' => 'mp4',
            str_starts_with($signature, 'RIFF') && substr($signature, 8, 4) === 'WAVE' => 'wav',
            default => null,
        };
        if ($format === null || ! in_array($file->getMimeType(), ['audio/webm', 'video/webm', 'audio/mp4', 'video/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav', 'audio/vnd.wave'], true)) {
            $this->reject();
        }
        try {
            $result = Process::timeout(15)->run([
                config('voice.ffprobe'), '-v', 'error', '-protocol_whitelist', 'file,pipe',
                '-show_entries', 'format=duration:stream=codec_type,duration:packet=pts_time,duration_time', '-of', 'json', $file->getRealPath(),
            ]);
            $probe = json_decode($result->output(), true, 16, JSON_THROW_ON_ERROR);
            $seconds = max(
                (float) ($probe['format']['duration'] ?? 0),
                max(array_map(fn (array $stream): float => (float) ($stream['duration'] ?? 0), $probe['streams'] ?? []) ?: [0]),
                max(array_map(fn (array $packet): float => (float) ($packet['pts_time'] ?? 0) + (float) ($packet['duration_time'] ?? 0), $probe['packets'] ?? []) ?: [0]),
            );
            $types = array_column($probe['streams'] ?? [], 'codec_type');
            if (! $result->successful() || ! is_finite($seconds) || $seconds <= 0 || $seconds > config('voice.max_segment_seconds') || $types !== ['audio']) {
                $this->reject();
            }
        } catch (Throwable) {
            $this->reject();
        }

        return ['format' => $format, 'seconds' => (int) ceil($seconds), 'checksum' => hash_file('sha256', $file->getRealPath())];
    }

    public function store(VoiceSession $session, UploadedFile $file): string
    {
        $path = 'voice/'.$session->clinic_id.'/'.$session->id.'/'.Str::uuid().'.enc';
        if (! Storage::disk('local')->put($path, Crypt::encryptString($file->getContent()))) {
            throw new RuntimeException('voice_storage_failed');
        }

        return $path;
    }

    public function read(string $path): string
    {
        return Crypt::decryptString(Storage::disk('local')->get($path));
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['audio' => 'Audio kosong, format tidak valid, durasi tidak dapat diverifikasi, atau segmen melebihi batas 65 detik. Rekam ulang segmen atau gunakan RME manual.']);
    }
}
