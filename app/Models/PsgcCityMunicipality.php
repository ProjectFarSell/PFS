<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PsgcCityMunicipality extends Model
{
    protected $guarded = [];

    protected $table = 'psgc_cities_municipalities';

    public function province(): BelongsTo
    {
        return $this->belongsTo(PsgcProvince::class, 'psgc_province_id');
    }

    public function barangays(): HasMany
    {
        return $this->hasMany(PsgcBarangay::class);
    }
}
