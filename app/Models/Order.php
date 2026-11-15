<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'fee_policy',
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
            'fee_policy' => 'array',
            'voucher_snapshot' => 'array',
            'discount_cents' => 'integer',
            'shipping_discount_cents' => 'integer',
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

        return ! $this->fulfillments()->whereHas('courierBooking', fn ($q) => $q->whereIn('status', ['submitting', 'unknown', 'booked']))->exists()
            && ! $this->fulfillments()->where(function ($q) {
                $q->whereNotIn('status', ['pending', 'accepted', 'ready', 'rejected'])->orWhereNotNull('rider_id');
            })->exists();
    }

    public function billableItems(): Collection
    {
        $this->loadMissing(['items', 'fulfillments']);
        if (in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Returned], true)) {
            return collect();
        }
        $excluded = $this->fulfillments->whereIn('status', [FulfillmentStatus::Rejected, FulfillmentStatus::Cancelled, FulfillmentStatus::Returned])->pluck('id');

        return $this->items->reject(fn ($item) => $excluded->contains($item->fulfillment_id));
    }

    public function billableShipping(): float
    {
        $this->loadMissing('fulfillments');
        if (in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Returned], true)) {
            return 0;
        }
        if ($this->fulfillments->isEmpty()) {
            return (float) $this->shipping_fee;
        }

        return (float) $this->fulfillments->reject(fn ($part) => in_array($part->status->value, ['rejected', 'cancelled', 'returned'], true))->sum('shipping_fee');
    }

    public function billableDiscountCents(): int
    {
        $this->loadMissing('fulfillments');
        if (in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Returned], true)) {
            return 0;
        }

        return $this->fulfillments->isEmpty() ? (int) $this->discount_cents + (int) $this->shipping_discount_cents
            : (int) $this->fulfillments->reject(fn ($part) => in_array($part->status->value, ['rejected', 'cancelled', 'returned'], true))
                ->sum(fn ($part) => $part->discount_cents + $part->shipping_discount_cents);
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

    public function replacementSource(): HasOne
    {
        return $this->hasOne(ReplacementRequest::class, 'new_order_id');
    }
}
