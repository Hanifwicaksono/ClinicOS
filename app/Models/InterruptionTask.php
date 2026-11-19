<?php

namespace App\Models;

use Database\Factories\InterruptionTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'visit_id', 'kind', 'status'])]
class InterruptionTask extends Model
{
    /** @use HasFactory<InterruptionTaskFactory> */
    use HasFactory;

    protected $hidden = ['clinical_assessment', 'patient_decision'];

    protected function casts(): array
    {
        return ['resolved_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function replacementTask(): BelongsTo
    {
        return $this->belongsTo(InterruptionTask::class, 'replacement_task_id');
    }
}
