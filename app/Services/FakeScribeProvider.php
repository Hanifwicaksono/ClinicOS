<?php

namespace App\Services;

class FakeScribeProvider implements ClinicalExtractionProvider, TranscriptionProvider
{
    private function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 503, 'Provider simulasi hanya tersedia untuk development/test.');
    }

    public function transcribe(string $audio, string $format, string $model): string
    {
        $this->guard();

        return 'SIMULASI, bukan transkripsi audio. Pasien mengatakan demam tiga hari. Dokter mengatakan evaluasi penyebab keluhan.';
    }

    /** @param array<int, string> $transcripts
     * @return array<string, mixed>
     */
    public function extract(array $transcripts, string $model): array
    {
        $this->guard();
        $soap = array_fill_keys(ClinicalDraftValidator::SOAP_FIELDS, ['text' => null, 'evidence' => []]);
        $soap['subjective'] = ['text' => 'Demam tiga hari.', 'evidence' => [['segment_no' => (int) array_key_first($transcripts), 'quote' => 'demam tiga hari']]];
        $soap['plan'] = ['text' => 'Evaluasi penyebab keluhan.', 'evidence' => [['segment_no' => (int) array_key_first($transcripts), 'quote' => 'evaluasi penyebab keluhan']]];

        return ['soap' => $soap, 'diagnoses' => [], 'medications' => [], 'procedures' => [], 'uncertain_items' => [], 'missing_information' => ['Simulasi development; diagnosis dan pemeriksaan belum tercatat.']];
    }
}
