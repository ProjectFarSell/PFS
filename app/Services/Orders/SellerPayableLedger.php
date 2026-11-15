<?php

namespace App\Services\Orders;

use App\Models\SettlementAdjustment;
use App\Models\ShipmentSettlement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

class SellerPayableLedger
{
    public function postSettlement(ShipmentSettlement $settlement): int
    {
        $shopId = $settlement->fulfillment()->value('shop_id');
        if ($shopId === null) {
            throw new LogicException('Cannot post a settlement without an identifiable shop.');
        }

        return $this->post('shipment_settlement_id', $settlement->id, (int) $shopId, (int) $settlement->seller_cents);
    }

    public function postAdjustment(SettlementAdjustment $adjustment): int
    {
        $source = DB::table('settlement_adjustments')
            ->join('shipment_settlements', 'shipment_settlements.id', '=', 'settlement_adjustments.shipment_settlement_id')
            ->join('fulfillments', 'fulfillments.id', '=', 'shipment_settlements.fulfillment_id')
            ->where('settlement_adjustments.id', $adjustment->id)
            ->select('fulfillments.shop_id', 'settlement_adjustments.seller_debit_cents')
            ->first();
        if (! $source || $source->shop_id === null) {
            throw new LogicException('Cannot post an adjustment without an identifiable shop.');
        }

        return $this->post('settlement_adjustment_id', $adjustment->id, (int) $source->shop_id, -(int) $source->seller_debit_cents);
    }

    private function post(string $sourceColumn, int $sourceId, int $shopId, int $amountCents): int
    {
        $ids = DB::table('seller_payable_entries')->where('shop_id', $shopId)->where($sourceColumn, $sourceId)->pluck('id')->all();
        app(FinancialLocks::class)->entries($shopId, $ids);
        try {
            return (int) DB::table('seller_payable_entries')->insertGetId([
                'shop_id' => $shopId,
                'amount_cents' => $amountCents,
                $sourceColumn => $sourceId,
                'created_at' => now(),
            ]);
        } catch (QueryException $exception) {
            $existing = DB::table('seller_payable_entries')->where($sourceColumn, $sourceId)->lockForUpdate()->first();
            if (! $existing) {
                throw $exception;
            }
            if ((int) $existing->shop_id !== $shopId || (int) $existing->amount_cents !== $amountCents) {
                throw new LogicException('The existing payable entry does not match its source.', previous: $exception);
            }

            return (int) $existing->id;
        }
    }
}
