<?php

namespace App\Models;

use Database\Factories\FollowUpEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'follow_up_id', 'actor_user_id', 'action', 'details', 'reason', 'request_id', 'request_fingerprint'])]
class FollowUpEvent extends Model
{
    /** @use HasFactory<FollowUpEventFactory> */
    use HasFactory;

    protected $hidden = ['details', 'reason', 'request_id', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
