<?php

namespace App\Services;

use App\Models\Patient;

class MedicalRecordNumberGenerator
{
    public function generate(Patient $patient): string
    {
        return sprintf('RM-%04d-%06d', $patient->clinic_id, $patient->id);
    }
}
