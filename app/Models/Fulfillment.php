<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
            'delivery_pin' => 'encrypted',
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

    public function deliveryBarangay(): BelongsTo
    {
        return $this->belongsTo(PsgcBarangay::class, 'delivery_barangay_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(FulfillmentEvent::class)->orderBy('id');
    }

    public function riderEarning(): HasOne
    {
        return $this->hasOne(RiderEarning::class);
    }

    public function riderRating(): HasOne
    {
        return $this->hasOne(RiderDeliveryRating::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(ShipmentFeedback::class);
    }

    public function amount(): float
    {
        return $this->status === FulfillmentStatus::Rejected ? 0 : (float) $this->subtotal + (float) $this->shipping_fee;
    }
}
