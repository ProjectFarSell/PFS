<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsgcBarangay extends Model
{
    protected $guarded = [];

    public function cityMunicipality(): BelongsTo
    {
        return $this->belongsTo(PsgcCityMunicipality::class, 'psgc_city_municipality_id');
    }
}
