<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    public const STATUSES = ['pending', 'queued', 'sending', 'retrying', 'sent', 'failed', 'skipped'];

    public const TYPES = [
        'order_confirmation' => 'Order confirmation',
        'seller_new_order' => 'New shop order',
        'shipment_update' => 'Buyer shipment update',
        'seller_shipment_update' => 'Seller shipment update',
        'rider_assignment' => 'Rider assignment',
        'rider_completion' => 'Rider completion',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'queued_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
