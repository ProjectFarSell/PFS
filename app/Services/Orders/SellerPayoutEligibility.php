<?php

namespace App\Services\Orders;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

class SellerPayoutEligibility
{
    private const OPEN_RETURNS = ['pending', 'seller_approved', 'seller_declined', 'approved'];

    public function isEligible(stdClass $entry, int $holdDays = 7): bool
    {
        if ($holdDays < 0 || $holdDays > 90) {
            throw new InvalidArgumentException('Payout hold days must be between 0 and 90.');
        }
        if ($entry->settlement_adjustment_id !== null) {
            return true;
        }

        $fulfillmentId = DB::table('shipment_settlements')->where('id', $entry->shipment_settlement_id)->value('fulfillment_id');
        if ($fulfillmentId === null) {
            return false;
        }

        $delivered = DB::table('fulfillment_events')->where('fulfillment_id', $fulfillmentId)
            ->where('status', 'delivered')->where('created_at', '<=', now()->subDays($holdDays))->exists();
        $openReturn = DB::table('return_requests')->join('order_items', 'order_items.id', '=', 'return_requests.order_item_id')
            ->where('order_items.fulfillment_id', $fulfillmentId)->whereIn('return_requests.status', self::OPEN_RETURNS)->exists();

        return $delivered && ! $openReturn;
    }
}
