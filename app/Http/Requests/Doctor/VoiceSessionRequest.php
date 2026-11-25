<?php

namespace App\Http\Requests\Doctor;

use App\Models\Visit;
use App\Services\ClinicalDraftValidator;
use App\Services\VoiceScribeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoiceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('visit');
        if (! $visit instanceof Visit || $this->user() === null) {
            return false;
        }
        app(VoiceScribeService::class)->authorize($visit, $this->user(), $this->route('voiceSession'), ! $this->isMethod('GET'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->routeIs('doctor.voice.store')) {
            return [
                'consent' => ['required', 'accepted'],
                'patient_confirmed' => ['required', 'accepted'],
                'consent_version' => ['required', Rule::in([config('voice.consent_version')])],
                'idempotency_key' => ['required', 'uuid'],
            ];
        }
        if ($this->routeIs('doctor.voice.segments')) {
            return ['audio' => ['required', 'file', 'max:'.config('voice.max_segment_kb')], 'segment_no' => ['required', 'integer', 'between:1,200']];
        }
        $action = $this->route('action');
        if ($action === 'stop') {
            return ['expected_segments' => ['required', 'integer', 'between:1,200']];
        }
        if (in_array($action, ['review', 'approve'], true)) {
            $rules = [
                'draft_version' => ['required', 'integer', 'min:1'],
                'soap' => ['required', 'array:'.implode(',', ClinicalDraftValidator::SOAP_FIELDS)],
            ];
            foreach (ClinicalDraftValidator::SOAP_FIELDS as $field) {
                $rules['soap.'.$field] = ['present', 'nullable', 'string', 'max:'.($field === 'chief_complaint' ? 5000 : 10000)];
            }
            if ($action === 'approve') {
                $rules += [
                    'review_confirmed' => ['required', 'accepted'],
                    'record_version' => ['required', 'integer', 'min:0'],
                    'fields' => ['required', 'array', 'min:1', 'max:5'],
                    'fields.*' => ['required', 'distinct', Rule::in(ClinicalDraftValidator::SOAP_FIELDS)],
                    'confirm_overwrite' => ['sometimes', 'boolean'],
                ];
            }

            return $rules;
        }

        return [];
    }
}
