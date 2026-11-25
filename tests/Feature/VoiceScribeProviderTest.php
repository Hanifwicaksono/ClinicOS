<?php

namespace Tests\Feature;

use App\Services\ClinicalDraftValidator;
use App\Services\FakeScribeProvider;
use App\Services\OpenAiScribeProvider;
use App\Services\VoiceScribeService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class VoiceScribeProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['voice.api_key' => 'test-key']);
    }

    public function test_transcription_uses_backend_multipart_and_rejects_provider_failure_without_leaking_content(): void
    {
        Http::fakeSequence('api.openai.com/v1/audio/transcriptions')
            ->push(['text' => 'Transkrip uji.'])->push(['error' => 'sensitive-provider-content'], 429);
        $this->assertSame('Transkrip uji.', app(OpenAiScribeProvider::class)->transcribe('audio-fixture', 'webm', 'configured-stt-model'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/audio/transcriptions'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && str_contains($request->body(), 'configured-stt-model') && str_contains($request->body(), 'audio-fixture'));
        try {
            app(OpenAiScribeProvider::class)->transcribe('audio-fixture', 'webm', 'configured-stt-model');
            $this->fail('A rate-limited provider must not produce a transcript.');
        } catch (RuntimeException $error) {
            $this->assertSame('voice_transcription_failed', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }

    public function test_exhausted_credit_is_identified_in_both_provider_stages_without_leaking_content(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 'credit_balance_exhausted', 'type' => 'insufficient_quota', 'message' => 'sensitive-provider-content']], 429)]);
        $provider = app(OpenAiScribeProvider::class);
        foreach (['transcribe', 'extract'] as $stage) {
            try {
                if ($stage === 'transcribe') {
                    $provider->transcribe('audio-fixture', 'wav', 'configured-model');
                } else {
                    $provider->extract([1 => 'Synthetic fixture'], 'configured-model');
                }
                $this->fail('Exhausted credit must stop processing.');
            } catch (RuntimeException $error) {
                $this->assertSame('voice_credit_balance_exhausted', $error->getMessage());
                $this->assertNull($error->getPrevious());
            }
        }
    }

    public function test_empty_and_malformed_transcription_responses_are_rejected(): void
    {
        foreach ([['text' => ''], ['text' => ['invalid']], ['unexpected' => 'value']] as $response) {
            Http::fake(['*' => Http::response($response)]);
            try {
                app(OpenAiScribeProvider::class)->transcribe('audio', 'wav', 'configured-model');
                $this->fail('Malformed provider response accepted.');
            } catch (RuntimeException $error) {
                $this->assertSame('voice_transcription_failed', $error->getMessage());
            }
        }
    }

    public function test_extraction_uses_strict_schema_and_keeps_transcript_in_untrusted_user_content(): void
    {
        $fake = new FakeScribeProvider;
        $transcripts = [1 => $fake->transcribe('', 'wav', 'simulation')];
        $payload = $fake->extract($transcripts, 'simulation');
        Http::fake(['*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode($payload)]]]])]);
        $this->assertSame($payload, app(OpenAiScribeProvider::class)->extract($transcripts, 'configured-extraction-model'));
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $data['model'] === 'configured-extraction-model'
                && $data['store'] === false && $data['response_format']['json_schema']['strict'] === true
                && $data['messages'][0]['role'] === 'system' && $data['messages'][1]['role'] === 'user'
                && str_contains($data['messages'][0]['content'], 'DATA TIDAK TEPERCAYA');
        });
    }

    public function test_invalid_json_refusal_and_truncated_extraction_are_rejected(): void
    {
        foreach ([
            ['finish_reason' => 'stop', 'message' => ['content' => 'not-json']],
            ['finish_reason' => 'length', 'message' => ['content' => '{}']],
            ['finish_reason' => 'stop', 'message' => ['refusal' => 'refused', 'content' => '{}']],
        ] as $choice) {
            Http::fake(['*' => Http::response(['choices' => [$choice]])]);
            try {
                app(OpenAiScribeProvider::class)->extract([1 => 'Fixture'], 'configured-model');
                $this->fail('Invalid extraction accepted.');
            } catch (RuntimeException $error) {
                $this->assertSame('voice_extraction_failed', $error->getMessage());
                $this->assertNull($error->getPrevious());
            }
        }
    }

    public function test_validator_allows_missing_findings_as_null_and_preserves_uncertainty(): void
    {
        [$payload, $transcripts] = $this->fixture();
        $payload['uncertain_items'] = ['Pembicara diagnosis belum dapat dipastikan.'];
        $valid = app(ClinicalDraftValidator::class)->validate($payload, $transcripts);
        $this->assertNull($valid['soap']['objective']['text']);
        $this->assertNull($valid['soap']['assessment']['text']);
        $this->assertSame([], $valid['diagnoses']);
        $this->assertNotEmpty($valid['uncertain_items']);
    }

    public function test_validator_rejects_missing_schema_keys_unknown_fields_and_unbacked_facts(): void
    {
        [$fixture, $transcripts] = $this->fixture();
        $cases = [];
        $payload = $fixture;
        unset($payload['soap']['assessment']);
        $cases[] = $payload;
        $cases[] = [...$fixture, 'clinic_id' => 99];
        $payload = $fixture;
        $payload['soap']['objective'] = ['text' => 'Tekanan darah 120/80.', 'evidence' => []];
        $cases[] = $payload;
        $payload['soap']['subjective']['evidence'][0]['quote'] = 'Kutipan yang tidak ada';
        $cases[] = $payload;
        $payload = $fixture;
        $payload['soap']['subjective']['evidence'][0]['segment_no'] = 99;
        $cases[] = $payload;
        $payload = $fixture;
        $payload['soap']['subjective']['text'] = 'Demam 39 derajat.';
        $cases[] = $payload;
        $payload = $fixture;
        $payload['medications'] = [['name' => 'Obat dugaan', 'dosage' => null, 'unit' => null, 'frequency' => null, 'route' => null, 'duration' => null, 'evidence' => []]];
        $cases[] = $payload;
        $payload = $fixture;
        $payload['procedures'] = [['name' => 'Tindakan', 'status' => 'charged', 'evidence' => []]];
        $cases[] = $payload;
        $payload = $fixture;
        $payload['diagnoses'] = [['name' => 'Malaria', 'code' => null, 'evidence' => $fixture['soap']['subjective']['evidence']]];
        $cases[] = $payload;
        foreach ($cases as $invalid) {
            try {
                app(ClinicalDraftValidator::class)->validate($invalid, $transcripts);
                $this->fail('Unsupported clinical data accepted.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('voice', $error->errors());
            }
        }
    }

    public function test_diagnosis_medication_and_procedure_candidates_require_evidence_and_remain_candidates(): void
    {
        [$payload, $transcripts] = $this->fixture();
        $transcripts[2] = 'Dokter: diagnosis kerja demam. Obat uji dosis 5 mg. Tindakan uji direncanakan.';
        $reference = [['segment_no' => 2, 'quote' => $transcripts[2]]];
        $payload['diagnoses'] = [['name' => 'Demam', 'code' => null, 'evidence' => $reference]];
        $payload['medications'] = [['name' => 'Obat uji', 'dosage' => '5', 'unit' => 'mg', 'frequency' => null, 'route' => null, 'duration' => null, 'evidence' => $reference]];
        $payload['procedures'] = [['name' => 'Tindakan uji', 'status' => 'planned', 'evidence' => $reference]];
        $this->assertSame($payload, app(ClinicalDraftValidator::class)->validate($payload, $transcripts));
        $payload['medications'][0]['dosage'] = '50';
        $this->expectException(ValidationException::class);
        app(ClinicalDraftValidator::class)->validate($payload, $transcripts);
    }

    public function test_production_requires_privacy_approval_and_cannot_enable_fake_provider(): void
    {
        $original = $this->app->environment();
        $this->app->instance('env', 'production');
        config(['voice.enabled' => true, 'voice.clinic_ids' => ['1'], 'voice.provider' => 'fake']);
        try {
            $this->assertFalse(app(VoiceScribeService::class)->enabledFor(1));
            config(['voice.provider' => 'openai', 'voice.transcription_model' => 'configured-stt', 'voice.extraction_model' => 'configured-extractor', 'voice.privacy_approved' => false]);
            $this->assertFalse(app(VoiceScribeService::class)->enabledFor(1));
            config(['voice.privacy_approved' => true]);
            $this->assertTrue(app(VoiceScribeService::class)->enabledFor(1));
        } finally {
            $this->app->instance('env', $original);
        }
    }

    /** @return array{array<string, mixed>, array<int, string>} */
    private function fixture(): array
    {
        $fake = new FakeScribeProvider;
        $transcripts = [1 => $fake->transcribe('', 'wav', 'simulation')];

        return [$fake->extract($transcripts, 'simulation'), $transcripts];
    }
}
