<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentSettlement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['earned_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Settlement snapshots cannot be overwritten.'));
        static::deleting(fn () => throw new \LogicException('Settlement snapshots must be retained.'));
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(SettlementAdjustment::class);
    }
}
