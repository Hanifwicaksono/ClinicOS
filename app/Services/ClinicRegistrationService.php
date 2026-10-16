<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ClinicRegistrationService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $validated */
    public function create(User $owner, array $validated, ?UploadedFile $logo = null): Clinic
    {
        return DB::transaction(function () use ($owner, $validated, $logo): Clinic {
            $clinicData = Arr::except($validated, [
                'logo', 'opening_time', 'closing_time', 'timezone', 'queue_prefix',
            ]);

            if ($logo !== null) {
                $clinicData['logo_path'] = $logo->store('clinic-logos', 'public');
            }

            $clinicData['owner_user_id'] = $owner->id;
            $clinic = Clinic::query()->create($clinicData);
            $clinic->settings()->create(Arr::only($validated, [
                'opening_time', 'closing_time', 'timezone', 'queue_prefix',
            ]));

            $owner->update(['clinic_id' => $clinic->id]);
            $this->auditLogger->recordForClinic($clinic, 'clinic.created', $clinic, actor: $owner);

            return $clinic;
        });
    }
}
