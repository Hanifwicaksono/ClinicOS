<?php

namespace App\Http\Requests;

use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;

class ChangeVisitWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('visit');

        return $visit instanceof Visit && $this->user()->can('changeWorkflow', [$visit, $this->route('action')]);
    }

    public function rules(): array
    {
        $action = $this->route('action');

        return ['request_id' => ['required', 'uuid'], 'workflow_version' => ['required', 'integer', 'min:0'],
            'record_version' => [in_array($action, ['ready', 'clear-ready'], true) ? 'nullable' : 'required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:2000'], 'pause_mode' => [$action === 'pause' ? 'required' : 'nullable', 'in:HOLD,RELEASE'],
            'estimated_return_at' => [$action === 'pause' ? 'required' : 'nullable', 'date_format:Y-m-d\TH:i'],
            'doctor_schedule_id' => [in_array($action, ['ready', 'resume'], true) ? 'required' : 'nullable', 'integer'],
            'patient_ready' => [$action === 'resume' ? 'required' : 'sometimes', 'accepted'],
            'clinical_delay_assessment' => ['nullable', 'string', 'max:5000']];
    }

    protected function getRedirectUrl(): string
    {
        return route('care-continuity.index');
    }
}
