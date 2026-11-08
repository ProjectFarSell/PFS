<?php

namespace App\Services\Orders;

use App\Enums\PaymentMethod;
use App\Models\Fulfillment;
use App\Models\ReturnRequest;
use App\Models\SettlementAdjustment;
use App\Models\ShipmentSettlement;

class SettlementService
{
    public function policy(): array
    {
        $policy = collect(['commission_bps', 'rider_pay_cents', 'cod_cost_cents', 'operating_cost_cents'])
            ->mapWithKeys(fn ($key) => [$key => (int) config('marketplace.'.$key)])->all();
        foreach ($policy as $key => $amount) {
            if ($amount < 0 || $amount > ($key === 'commission_bps' ? 10000 : 100000000)) {
                throw new \RuntimeException('Invalid marketplace fee configuration.');
            }
        }

        return $policy;
    }

    public function riderPay(Fulfillment $part): float
    {
        if ($part->usesExternalCourier()) {
            return 0;
        }

        return $part->fee_policy ? $part->fee_policy['rider_pay_cents'] / 100 : (float) $part->shipping_fee;
    }

    /** Caller holds the parent order lock. No historic orders are repriced. */
    public function complete(Fulfillment $part): void
    {
        if (! $part->fee_policy || $part->order->payment_method !== PaymentMethod::Cod || ! $part->cod_collected_at) {
            return;
        }
        $policy = $part->fee_policy;
        $external = $part->usesExternalCourier();
        $booking = $external ? $part->courierBooking : null;
        if ($external && (! $booking || $booking->isSandbox())) {
            return;
        }
        if ($external && ($booking->cost_cents === null || $booking->status !== 'booked')) {
            throw new \RuntimeException('Courier cost must be verified before settlement.');
        }
        $riderPay = $external ? 0 : $policy['rider_pay_cents'];
        $courierCost = $external ? $booking->cost_cents : 0;
        $goods = (int) round((float) $part->subtotal * 100);
        $delivery = (int) round((float) $part->shipping_fee * 100);
        $commission = $this->commission($goods, $policy['commission_bps']);
        // COD handling and operating costs occur once per order, even for multi-shop orders.
        $first = ! ShipmentSettlement::whereHas('fulfillment', fn ($q) => $q->where('order_id', $part->order_id))->exists();
        $cod = $first ? $policy['cod_cost_cents'] : 0;
        $operating = $first ? $policy['operating_cost_cents'] : 0;
        ShipmentSettlement::firstOrCreate(['fulfillment_id' => $part->id], [
            'goods_cents' => $goods, 'delivery_cents' => $delivery,
            'commission_bps' => $policy['commission_bps'], 'commission_cents' => $commission,
            'seller_cents' => $goods - $commission, 'rider_cents' => $riderPay, 'courier_cost_cents' => $courierCost,
            'cod_cost_cents' => $cod, 'operating_cost_cents' => $operating,
            'contribution_cents' => $commission + $delivery - $riderPay - $courierCost - $cod - $operating,
            'earned_at' => now(),
        ]);
        foreach (ReturnRequest::whereHas('item', fn ($q) => $q->where('fulfillment_id', $part->id))->where('status', 'refunded')->orderBy('id')->get() as $case) {
            $this->refund($case);
        }
    }

    /** Called under the same parent order lock; adjustments never overwrite the sale. */
    public function refund(ReturnRequest $case): void
    {
        $settlement = ShipmentSettlement::where('fulfillment_id', $case->item->fulfillment_id)->first();
        if (! $settlement || SettlementAdjustment::where('return_request_id', $case->id)->exists()) {
            return;
        }
        $amount = (int) round((float) $case->approved_amount * 100);
        $refunded = (int) $settlement->adjustments()->sum('refund_cents');
        if ($refunded + $amount > $settlement->goods_cents) {
            throw new \RuntimeException('Reimbursement exceeds the settled goods amount.');
        }
        $reversal = $this->commission($refunded + $amount, $settlement->commission_bps)
            - (int) $settlement->adjustments()->sum('commission_reversal_cents');
        $settlement->adjustments()->create([
            'return_request_id' => $case->id, 'refund_cents' => $amount,
            'commission_reversal_cents' => $reversal, 'seller_debit_cents' => $amount - $reversal,
        ]);
    }

    private function commission(int $cents, int $bps): int
    {
        return intdiv($cents * $bps + 5000, 10000);
    }
}
