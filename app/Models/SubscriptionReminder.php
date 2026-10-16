<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'subscription_period_id', 'owner_user_id', 'milestone_days', 'due_on', 'notification_id', 'database_notified_at', 'mailed_at', 'sent_at', 'failed_at', 'error_type'])]
class SubscriptionReminder extends Model
{
    protected function casts(): array
    {
        return [
            'milestone_days' => 'integer',
            'due_on' => 'immutable_date',
            'database_notified_at' => 'immutable_datetime',
            'mailed_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPeriod::class, 'subscription_period_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
