<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PsgcRegion extends Model
{
    protected $guarded = [];

    public function provinces(): HasMany
    {
        return $this->hasMany(PsgcProvince::class);
    }
}
