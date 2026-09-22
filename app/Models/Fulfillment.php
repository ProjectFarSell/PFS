<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fulfillment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => FulfillmentStatus::class,
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'cod_collected_at' => 'datetime',
            'stock_released_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(RiderProfile::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(FulfillmentEvent::class)->orderBy('id');
    }

    public function amount(): float
    {
        return $this->status === FulfillmentStatus::Rejected ? 0 : (float) $this->subtotal + (float) $this->shipping_fee;
    }
}
