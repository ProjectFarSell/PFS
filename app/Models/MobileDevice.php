<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileDevice extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['fcm_token', 'token_hash'];

    protected function casts(): array
    {
        return ['fcm_token' => 'encrypted', 'enabled_events' => 'array', 'enabled' => 'boolean'];
    }
}
