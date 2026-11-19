<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientReminderDelivery extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['queued_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime', 'broadcast_sent_at' => 'immutable_datetime', 'generation' => 'integer'];
    }
}
