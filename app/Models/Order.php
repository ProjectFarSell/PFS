<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'address_id',
        'rider_id',
        'number',
        'status',
        'payment_method',
        'guest_email',
        'guest_name',
        'ship_to',
        'subtotal',
        'shipping_fee',
        'total',
        'stock_reserved_at',
        'stock_released_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'stock_reserved_at' => 'datetime',
            'stock_released_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function canBuyerCancel(): bool
    {
        if (! in_array($this->status, [OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::Confirmed, OrderStatus::Packed], true)) {
            return false;
        }

        return ! $this->fulfillments()->where(function ($q) {
            $q->whereNotIn('status', ['pending', 'accepted', 'ready', 'rejected'])->orWhereNotNull('rider_id');
        })->exists();
    }

    public function billableItems(): Collection
    {
        $this->loadMissing(['items', 'fulfillments']);
        if ($this->status === OrderStatus::Cancelled) {
            return collect();
        }
        $excluded = $this->fulfillments->whereIn('status', [FulfillmentStatus::Rejected, FulfillmentStatus::Cancelled])->pluck('id');

        return $this->items->reject(fn ($item) => $excluded->contains($item->fulfillment_id));
    }

    public function billableShipping(): float
    {
        $this->loadMissing('fulfillments');
        if ($this->status === OrderStatus::Cancelled) {
            return 0;
        }
        if ($this->fulfillments->isEmpty()) {
            return (float) $this->shipping_fee;
        }

        return (float) $this->fulfillments->reject(fn ($part) => in_array($part->status->value, ['rejected', 'cancelled'], true))->sum('shipping_fee');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(RiderProfile::class, 'rider_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }
}
