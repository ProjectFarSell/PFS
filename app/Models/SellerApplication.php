<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerApplication extends Model
{
    protected $fillable = ['shop_name', 'city', 'psgc_city_municipality_id', 'category_ids', 'tagline', 'description', 'status', 'revision', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'category_ids' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function cityMunicipality(): BelongsTo
    {
        return $this->belongsTo(PsgcCityMunicipality::class, 'psgc_city_municipality_id');
    }
}
