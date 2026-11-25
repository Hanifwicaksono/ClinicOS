<?php

namespace App\Services;

interface TranscriptionProvider
{
    public function transcribe(string $audio, string $format, string $model): string;
}
