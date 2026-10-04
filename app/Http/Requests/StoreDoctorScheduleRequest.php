<?php

namespace App\Http\Requests;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDoctorScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $schedule = $this->route('doctor_schedule');

        return $schedule instanceof DoctorSchedule
            ? $this->user()->can('update', $schedule)
            : $this->user()->can('create', DoctorSchedule::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'doctor_id' => [
                'required',
                Rule::exists(Doctor::class, 'id')->where('clinic_id', $this->user()->clinic_id),
            ],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i', 'before:end_time'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'quota' => ['required', 'integer', 'min:1', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $settings = $this->user()->clinic?->settings;

            if ($settings !== null
                && ($this->string('start_time')->toString() < substr($settings->opening_time, 0, 5)
                    || $this->string('end_time')->toString() > substr($settings->closing_time, 0, 5))) {
                $validator->errors()->add('start_time', 'Jadwal harus berada di dalam jam operasional klinik.');
            }

            $schedule = $this->route('doctor_schedule');
            $overlap = DoctorSchedule::query()
                ->where('doctor_id', $this->integer('doctor_id'))
                ->where('day_of_week', $this->integer('day_of_week'))
                ->where('start_time', '<', $this->string('end_time')->toString())
                ->where('end_time', '>', $this->string('start_time')->toString())
                ->when(
                    $schedule instanceof DoctorSchedule,
                    fn ($query) => $query->whereKeyNot($schedule->getKey()),
                )
                ->exists();

            if ($overlap) {
                $validator->errors()->add('start_time', 'Jadwal bertabrakan dengan sesi dokter yang sudah ada.');
            }
        });
    }
}
