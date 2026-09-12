<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PsgcProvince extends Model
{
    protected $guarded = [];

    public function region(): BelongsTo
    {
        return $this->belongsTo(PsgcRegion::class, 'psgc_region_id');
    }

    public function citiesMunicipalities(): HasMany
    {
        return $this->hasMany(PsgcCityMunicipality::class);
    }
}
