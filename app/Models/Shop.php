<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'tagline',
        'city',
        'psgc_city_municipality_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Shop $shop) {
            $shop->slug ??= Str::slug($shop->name).'-'.Str::lower(Str::random(4));
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function psgcCityMunicipality(): BelongsTo
    {
        return $this->belongsTo(PsgcCityMunicipality::class);
    }

    public function scopeInCity(Builder $query, int $cityId): Builder
    {
        return $query->where('psgc_city_municipality_id', $cityId);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
