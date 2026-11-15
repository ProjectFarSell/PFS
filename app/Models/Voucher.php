<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (self $voucher) {
            $voucher->code = strtoupper(trim($voucher->code));
        });
    }

    protected function casts(): array
    {
        return ['value' => 'integer', 'minimum_spend_cents' => 'integer', 'maximum_discount_cents' => 'integer',
            'usage_limit' => 'integer', 'per_buyer_limit' => 'integer', 'version' => 'integer',
            'starts_at' => 'datetime', 'expires_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
