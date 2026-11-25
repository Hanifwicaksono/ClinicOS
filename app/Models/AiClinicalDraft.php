<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['session_id', 'payload', 'edited_soap', 'version', 'review_status', 'reviewed_by_user_id', 'applied_record_version', 'applied_at'])]
class AiClinicalDraft extends Model
{
    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'edited_soap' => 'encrypted:array', 'version' => 'integer', 'applied_at' => 'datetime'];
    }
}
