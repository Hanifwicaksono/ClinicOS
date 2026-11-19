<?php

namespace App\Models;

use App\VisitStatus;
use Database\Factories\CareNotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'visit_id', 'recipient_user_id', 'kind', 'generation', 'notification_id'])]
class CareNotificationDelivery extends Model
{
    /** @use HasFactory<CareNotificationDeliveryFactory> */
    use HasFactory;

    protected $hidden = ['notification_id'];

    protected function casts(): array
    {
        return ['database_sent_at' => 'immutable_datetime', 'broadcast_dispatched_at' => 'immutable_datetime'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function canDeliver(User $user): bool
    {
        if (! $user->is_active || ! $user->hasVerifiedEmail() || ! $user->hasRole('Doctor') || $user->clinic_id !== $this->clinic_id || $user->id !== $this->recipient_user_id) {
            return false;
        }
        $visit = $this->visit;
        if ($this->kind === 'lead_assigned') {
            return $visit->caseLeads()->whereKey($this->generation)->whereNull('revoked_at')->where('starts_at', '<=', now())->where('ends_at', '>', now())
                ->whereHas('doctor', fn ($query) => $query->where('user_id', $user->id)->where('is_active', true))->exists();
        }
        if ($this->kind === 'handoff_requested') {
            return $visit->continuations()->whereKey($this->generation)->where('status', 'REQUESTED')->where('expires_at', '>', now())
                ->whereHas('receivingDoctor', fn ($query) => $query->where('user_id', $user->id)->where('is_active', true))->exists();
        }
        if (str_starts_with($this->kind, 'pause_')) {
            if ($visit->status !== VisitStatus::Paused || $visit->pause_generation !== (int) $this->generation) {
                return false;
            }

            return $this->kind === 'pause_escalated' ? $visit->hasClinicalLead($user, 'CARE_COORDINATION') : $visit->doctor->user_id === $user->id;
        }

        return $user->can('coordinate', $visit);
    }
}
