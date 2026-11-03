<?php

namespace App\Services\Orders;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Notifications\BuyerInbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnRequestService
{
    public function submit(OrderItem $item, User $buyer, array $data): ReturnRequest
    {
        return DB::transaction(function () use ($item, $buyer, $data) {
            $order = Order::query()->lockForUpdate()->findOrFail($item->order_id);
            $item = OrderItem::query()->findOrFail($item->id);
            abort_unless($buyer->role === UserRole::Buyer && $order->user_id === $buyer->id, 403);
            if (! in_array($item->fulfillment?->status->value, ['delivered', 'completed'], true)) {
                throw ValidationException::withMessages(['return' => 'Requests are available after this shipment is delivered.']);
            }
            if ($item->returnRequest()->exists()) {
                throw ValidationException::withMessages(['return' => 'A request already exists for this item. View it in Returns & refunds.']);
            }
            if ($data['quantity'] > $item->qty) {
                throw ValidationException::withMessages(['quantity' => 'Quantity cannot exceed the quantity purchased.']);
            }
            $case = ReturnRequest::query()->create([
                'order_item_id' => $item->id, 'quantity' => $data['quantity'], 'reason' => $data['reason'],
                'details' => $data['details'], 'evidence_path' => $data['evidence_path'] ?? null,
                'requested_amount' => (int) round((float) $item->unit_price * 100) * $data['quantity'] / 100,
            ]);
            $case->events()->create(['actor_id' => $buyer->id, 'status' => 'pending', 'note' => 'Buyer submitted a return/refund request.']);
            app(BuyerInbox::class)->record($order, 'return:'.$case->id.':submitted', 'Return/refund request submitted', 'Your request for '.$item->name.' is awaiting review. Open Returns & refunds from your order to follow its progress.');

            return $case;
        }, 3);
    }

    public function review(ReturnRequest $case, User $actor, array $data): void
    {
        DB::transaction(function () use ($case, $actor, $data) {
            $order = Order::query()->lockForUpdate()->findOrFail($case->item->order_id);
            $case = ReturnRequest::query()->lockForUpdate()->findOrFail($case->id);
            $seller = $actor->role === UserRole::Seller && $case->item->fulfillment?->shop?->user_id === $actor->id;
            abort_unless($seller || $actor->role === UserRole::Admin, 403);
            $action = $data['action'];
            if ($seller) {
                if ($case->status !== 'pending' || ! in_array($action, ['seller_approved', 'seller_declined'], true)) {
                    $this->invalid();
                }
            } elseif ($action === 'refunded') {
                if ($case->status !== 'approved') {
                    $this->invalid();
                }
                if ($case->return_required && ! ($data['return_received'] ?? false)) {
                    throw ValidationException::withMessages(['return_received' => 'Confirm the returned goods have been received before recording reimbursement.']);
                }
                if ($order->payment_method->value !== 'cod' || ! $case->item->fulfillment?->cod_collected_at) {
                    throw ValidationException::withMessages(['refund' => 'Only collected COD payments can be reimbursed here. Demo payments are not real payments.']);
                }
                $case->refunded_at = now();
                $case->refund_reference = $data['refund_reference'];
                if ($case->return_required) {
                    $case->return_received_at = now();
                }
            } else {
                if (! in_array($case->status, ['pending', 'seller_approved', 'seller_declined'], true) || ! in_array($action, ['approved', 'rejected'], true)) {
                    $this->invalid();
                }
                if ($action === 'approved') {
                    if (round((float) $data['approved_amount'] * 100) > round((float) $case->requested_amount * 100)) {
                        throw ValidationException::withMessages(['approved_amount' => 'Approval cannot exceed the requested merchandise amount.']);
                    }
                    $case->approved_amount = $data['approved_amount'];
                    $case->return_required = (bool) ($data['return_required'] ?? false);
                }
            }
            $case->status = $action;
            $case->save();
            $event = $case->events()->create(['actor_id' => $actor->id, 'status' => $action, 'note' => $data['note']]);
            app(BuyerInbox::class)->record($order, 'return-event:'.$event->id, 'Return/refund update', $case->item->name.': '.$case->label().'.');
        }, 3);
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['return' => 'This request has changed or that action is unavailable. Refresh and try again.']);
    }
}
