<?php

namespace App\Services\Orders;

use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use LogicException;

class SellerPayableBalance
{
    public function __construct(private readonly SellerPayoutEligibility $eligibility) {}

    /** @return array{available_cents: int, in_unpaid_batch_cents: int, ineligible_held_cents: int} */
    public function forShop(Shop $shop, int $holdDays = 7): array
    {
        $balance = ['available_cents' => 0, 'in_unpaid_batch_cents' => 0, 'ineligible_held_cents' => 0];
        $entries = DB::table('seller_payable_entries as entries')
            ->leftJoin('seller_payout_items as items', 'items.active_entry_id', '=', 'entries.id')
            ->leftJoin('seller_payouts as payouts', 'payouts.id', '=', 'items.seller_payout_id')
            ->where('entries.shop_id', $shop->id)
            ->select('entries.*', 'payouts.status as payout_status')->orderBy('entries.id')->get();

        foreach ($entries as $entry) {
            if ($entry->payout_status === 'paid') {
                continue;
            }
            if ($entry->payout_status !== null && $entry->payout_status !== 'unpaid') {
                throw new LogicException('A cancelled payout cannot retain an active entry.');
            }
            $amount = (int) $entry->amount_cents;
            if ($entry->payout_status === 'unpaid') {
                $balance['in_unpaid_batch_cents'] += $amount;
            }
            if ($this->eligibility->isEligible($entry, $holdDays)) {
                $balance['available_cents'] += $amount;
            } elseif ($entry->shipment_settlement_id !== null) {
                $balance['ineligible_held_cents'] += $amount;
            }
        }

        return $balance;
    }
}
