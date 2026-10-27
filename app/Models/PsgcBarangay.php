<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PsgcBarangay extends Model
{
    protected $guarded = [];

    public function cityMunicipality(): BelongsTo
    {
        return $this->belongsTo(PsgcCityMunicipality::class, 'psgc_city_municipality_id');
    }

    public function riderAssignment(): HasOne
    {
        return $this->hasOne(RiderBarangayAssignment::class);
    }
}
