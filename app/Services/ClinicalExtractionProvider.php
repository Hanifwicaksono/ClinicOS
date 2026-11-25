<?php

namespace App\Services;

interface ClinicalExtractionProvider
{
    /** @param array<int, string> $transcripts
     * @return array<string, mixed>
     */
    public function extract(array $transcripts, string $model): array;
}
