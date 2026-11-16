<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealtimeRevocation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime', 'failed_at' => 'datetime'];
    }
}
