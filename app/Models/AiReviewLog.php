<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['draft_id', 'actor_id', 'action', 'changes'])]
class AiReviewLog extends Model
{
    protected function casts(): array
    {
        return ['changes' => 'encrypted:array'];
    }
}
