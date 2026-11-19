<?php

namespace App\Models;

use Database\Factories\VisitWorkflowEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'visit_id', 'actor_user_id', 'action', 'from_status', 'to_status', 'workflow_version', 'record_version', 'reason', 'details', 'request_id', 'request_fingerprint'])]
class VisitWorkflowEvent extends Model
{
    /** @use HasFactory<VisitWorkflowEventFactory> */
    use HasFactory;

    protected $hidden = ['reason', 'details', 'request_id', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
