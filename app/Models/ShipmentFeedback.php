<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentFeedback extends Model
{
    protected $table = 'shipment_feedback';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['seller_service_rating' => 'integer', 'delivery_speed_rating' => 'integer'];
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}
