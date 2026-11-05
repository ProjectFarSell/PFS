<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    public const STATUSES = ['pending', 'seller_approved', 'seller_declined', 'approved', 'rejected', 'refunded'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['requested_amount' => 'decimal:2', 'approved_amount' => 'decimal:2', 'return_required' => 'boolean', 'return_received_at' => 'datetime', 'refunded_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReturnRequestEvent::class)->orderBy('id');
    }

    public function label(): string
    {
        return match ($this->status) {
            'pending' => 'Awaiting review', 'seller_approved' => 'Seller recommends approval',
            'seller_declined' => 'Seller disputes request', 'approved' => 'Approved — awaiting reimbursement',
            'rejected' => 'Rejected by admin', 'refunded' => 'Reimbursement recorded',
        };
    }
}
