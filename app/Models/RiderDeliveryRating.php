<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderDeliveryRating extends Model
{
    public const TAGS = ['Friendly', 'Good hygiene', 'Good communication', 'Reliable', 'Regular delivery updates', 'Helpful', 'Responsive', 'Professional', 'Fast delivery'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'tags' => 'array'];
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function riderProfile(): BelongsTo
    {
        return $this->belongsTo(RiderProfile::class);
    }
}
