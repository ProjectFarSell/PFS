<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'payload' => 'array', 'expires_at' => 'datetime', 'broadcast_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
