<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['session_id', 'segment_id', 'transcript_text', 'version'])]
class VoiceTranscript extends Model
{
    protected function casts(): array
    {
        return ['transcript_text' => 'encrypted'];
    }
}
