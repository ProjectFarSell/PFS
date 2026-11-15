<?php

namespace Tests\Concerns;

use App\Models\ReturnRequest;
use App\Models\SellerPayout;
use App\Services\Orders\CreateSellerPayoutBatch;
use App\Services\Orders\ReturnRequestService;
use Illuminate\Support\Facades\DB;

trait PayableScenario
{
    use ShipmentScenario;

    private function payableScenario(): array
    {
        $s = $this->shipmentScenario();
        $this->completeShipment($s);
        DB::table('fulfillment_events')->where('fulfillment_id', $s['part']->id)->where('status', 'delivered')->update(['created_at' => now()->subDays(8)]);
        $this->actingAs($s['admin']);

        return $s;
    }

    private function payoutBatch(array $s): SellerPayout
    {
        $result = app(CreateSellerPayoutBatch::class)->create($s['shop'], $s['admin']);

        return SellerPayout::findOrFail($result['id']);
    }

    private function refundPayable(array $s, string $amount = '50.00'): ReturnRequest
    {
        $case = ReturnRequest::create(['order_item_id' => $s['part']->items()->sole()->id, 'quantity' => 1,
            'reason' => 'damaged', 'details' => 'Damaged goods returned.', 'requested_amount' => $amount,
            'approved_amount' => $amount, 'status' => 'approved']);
        app(ReturnRequestService::class)->review($case, $s['admin'], ['action' => 'refunded', 'refund_reference' => 'MANUAL-REFUND', 'note' => 'Money returned outside the platform.']);

        return $case;
    }
}
