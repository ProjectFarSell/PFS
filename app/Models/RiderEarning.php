<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderEarning extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'earned_at' => 'datetime'];
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function riderProfile(): BelongsTo
    {
        return $this->belongsTo(RiderProfile::class);
    }
}
