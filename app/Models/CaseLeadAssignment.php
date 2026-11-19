<?php

namespace App\Models;

use Database\Factories\CaseLeadAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'visit_id', 'doctor_id', 'approver_user_id', 'role', 'purpose', 'verification_evidence', 'starts_at', 'ends_at', 'request_id', 'request_fingerprint'])]
class CaseLeadAssignment extends Model
{
    /** @use HasFactory<CaseLeadAssignmentFactory> */
    use HasFactory;

    protected $hidden = ['verification_evidence', 'revocation_reason', 'request_id', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
