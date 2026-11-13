<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileTicket extends Model
{
    protected $table = 'mobile_auth_tickets';

    protected $guarded = ['id'];

    protected $hidden = ['secret_hash', 'context_hash', 'identity_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }
}
