<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileQuote extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'encrypted:array', 'expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }
}
