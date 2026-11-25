<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ClinicalDraftValidator
{
    public const SOAP_FIELDS = ['chief_complaint', 'subjective', 'objective', 'assessment', 'plan'];

    /** @return array<string, mixed> */
    public function schema(): array
    {
        $text = ['type' => ['string', 'null'], 'maxLength' => 10000];
        $evidence = ['type' => 'array', 'maxItems' => 30, 'items' => $this->object([
            'segment_no' => ['type' => 'integer'],
            'quote' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 4000],
        ])];
        $fact = $this->object(['text' => $text, 'evidence' => $evidence]);
        $list = fn (array $item): array => ['type' => 'array', 'maxItems' => 30, 'items' => $item];

        return $this->object([
            'soap' => $this->object(array_fill_keys(self::SOAP_FIELDS, $fact)),
            'diagnoses' => $list($this->object(['name' => ['type' => 'string'], 'code' => $text, 'evidence' => $evidence])),
            'medications' => $list($this->object([
                'name' => ['type' => 'string'], 'dosage' => $text, 'unit' => $text,
                'frequency' => $text, 'route' => $text, 'duration' => $text, 'evidence' => $evidence,
            ])),
            'procedures' => $list($this->object([
                'name' => ['type' => 'string'], 'status' => ['type' => 'string', 'enum' => ['planned', 'performed', 'uncertain']], 'evidence' => $evidence,
            ])),
            'uncertain_items' => $list(['type' => 'string', 'maxLength' => 2000]),
            'missing_information' => $list(['type' => 'string', 'maxLength' => 2000]),
        ]);
    }

    /** @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    private function object(array $properties): array
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false];
    }

    /** @param array<string, mixed> $payload
     * @param  array<int, string>  $transcripts
     * @return array<string, mixed>
     */
    public function validate(array $payload, array $transcripts): array
    {
        $this->check($payload, $this->schema());
        foreach ($payload['soap'] as $fact) {
            $this->evidence($fact['text'], $fact['evidence'], $transcripts);
        }
        foreach (['diagnoses', 'medications', 'procedures'] as $group) {
            foreach ($payload[$group] as $fact) {
                $this->evidence(implode(' ', array_filter(array_diff_key($fact, ['evidence' => true, 'status' => true]), 'is_string')), $fact['evidence'], $transcripts);
                $source = mb_strtolower(str_replace(',', '.', implode(' ', array_column($fact['evidence'], 'quote'))));
                foreach (array_diff_key($fact, ['evidence' => true, 'status' => true]) as $value) {
                    if (filled($value) && ! str_contains($source, mb_strtolower(str_replace(',', '.', $value)))) {
                        $this->reject();
                    }
                }
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $schema */
    private function check(mixed $value, array $schema): void
    {
        $valid = false;
        foreach ((array) $schema['type'] as $type) {
            $valid = $valid || match ($type) {
                'null' => $value === null,
                'string' => is_string($value) && mb_strlen($value) <= ($schema['maxLength'] ?? 10000) && mb_strlen($value) >= ($schema['minLength'] ?? 0),
                'integer' => is_int($value),
                'object' => is_array($value) && array_diff(array_keys($value), array_keys($schema['properties'])) === [] && array_diff($schema['required'], array_keys($value)) === [],
                'array' => is_array($value) && array_is_list($value) && count($value) <= $schema['maxItems'],
                default => false,
            };
        }
        if (! $valid || (isset($schema['enum']) && ! in_array($value, $schema['enum'], true))) {
            $this->reject();
        }
        if ($schema['type'] === 'object') {
            foreach ($schema['properties'] as $key => $child) {
                $this->check($value[$key], $child);
            }
        } elseif ($schema['type'] === 'array') {
            foreach ($value as $item) {
                $this->check($item, $schema['items']);
            }
        }
    }

    /** @param list<array{segment_no:int, quote:string}> $evidence
     * @param  array<int, string>  $transcripts
     */
    private function evidence(?string $text, array $evidence, array $transcripts): void
    {
        if (blank($text)) {
            if ($evidence !== []) {
                $this->reject();
            }

            return;
        }
        if ($evidence === []) {
            $this->reject();
        }
        foreach ($evidence as $reference) {
            if (! isset($transcripts[$reference['segment_no']]) || ! str_contains($transcripts[$reference['segment_no']], $reference['quote'])) {
                $this->reject();
            }
        }
        preg_match_all('/\d+(?:[.,]\d+)?/u', $text, $numbers);
        preg_match_all('/\d+(?:[.,]\d+)?/u', str_replace(',', '.', implode(' ', array_column($evidence, 'quote'))), $sourceNumbers);
        foreach ($numbers[0] as $number) {
            if (! in_array(str_replace(',', '.', $number), $sourceNumbers[0], true)) {
                $this->reject();
            }
        }
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['voice' => 'Hasil AI tidak memenuhi skema atau bukti transkrip. Ulangi pemrosesan atau gunakan RME manual.']);
    }
}
