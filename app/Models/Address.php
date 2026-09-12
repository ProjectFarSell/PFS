<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'line1',
        'city',
        'region',
        'psgc_region_id',
        'psgc_province_id',
        'psgc_city_municipality_id',
        'psgc_barangay_id',
        'postal_code',
        'phone',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function psgcRegion(): BelongsTo
    {
        return $this->belongsTo(PsgcRegion::class);
    }

    public function psgcProvince(): BelongsTo
    {
        return $this->belongsTo(PsgcProvince::class);
    }

    public function psgcCityMunicipality(): BelongsTo
    {
        return $this->belongsTo(PsgcCityMunicipality::class);
    }

    public function psgcBarangay(): BelongsTo
    {
        return $this->belongsTo(PsgcBarangay::class);
    }

    public function formatted(): string
    {
        return collect([
            $this->line1,
            $this->psgcBarangay?->name,
            $this->psgcCityMunicipality?->name ?? $this->city,
            $this->psgcProvince?->name,
            $this->psgcRegion?->name ?? $this->region,
            $this->postal_code,
        ])->filter()->implode(', ');
    }
}
