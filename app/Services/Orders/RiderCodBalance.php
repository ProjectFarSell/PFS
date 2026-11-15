<?php

namespace App\Services\Orders;

use App\Models\Fulfillment;
use App\Models\RiderCodRemittance;
use App\Models\RiderProfile;
use App\Support\Money;

class RiderCodBalance
{
    public function forRider(RiderProfile $rider): array
    {
        $collected = 0;
        Fulfillment::where('rider_id', $rider->id)->where('delivery_method', 'internal')->whereNotNull('cod_collected_at')
            ->whereHas('order', fn ($query) => $query->where('payment_method', 'cod'))->select(['id', 'subtotal', 'shipping_fee', 'discount_cents', 'shipping_discount_cents'])
            ->orderBy('id')->chunkById(200, function ($parts) use (&$collected) {
                foreach ($parts as $part) {
                    $collected += Money::cents($part->subtotal) + Money::cents($part->shipping_fee) - $part->discount_cents - $part->shipping_discount_cents;
                }
            });
        $remitted = (int) RiderCodRemittance::where('rider_profile_id', $rider->id)->whereNull('voided_at')->sum('amount_cents');

        return ['collected_cents' => $collected, 'remitted_cents' => $remitted, 'owed_cents' => $collected - $remitted];
    }
}
