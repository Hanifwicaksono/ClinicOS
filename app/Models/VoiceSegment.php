<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['session_id', 'segment_no', 'audio_path', 'audio_format', 'checksum', 'duration_seconds', 'processing_status', 'purged_at'])]
class VoiceSegment extends Model
{
    protected function casts(): array
    {
        return ['purged_at' => 'datetime', 'segment_no' => 'integer', 'duration_seconds' => 'integer'];
    }
}
