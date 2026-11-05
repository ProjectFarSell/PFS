<?php

namespace App\Services\Orders;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\BuyerInbox;
use App\Services\Notifications\OrderNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrder
{
    public function handle(Order $order, User $buyer, string $reason): void
    {
        DB::transaction(function () use ($order, $buyer, $reason) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($buyer->role === UserRole::Buyer && $order->user_id === $buyer->id, 403);
            if ($order->cancelled_at) {
                return;
            }
            if (! $order->canBuyerCancel()) {
                throw ValidationException::withMessages(['cancellation' => 'This order can no longer be cancelled. Cancellation is available only before rider assignment.']);
            }
            $parts = $order->fulfillments()->orderBy('id')->lockForUpdate()->get();
            foreach ($parts as $part) {
                if ($part->status === FulfillmentStatus::Rejected) {
                    continue;
                }
                if ($order->stock_reserved_at && ! $order->stock_released_at && ! $part->stock_released_at) {
                    app(StockReservationService::class)->releaseItems($part->items()->orderBy('product_id')->get());
                    $part->stock_released_at = now();
                }
                $part->status = FulfillmentStatus::Cancelled;
                $part->save();
                $event = $part->events()->create(['actor_id' => $buyer->id, 'status' => 'cancelled', 'note' => $reason]);
                app(OrderNotificationService::class)->shipmentChanged($event);
                DB::table('rider_delivery_requests')->where('fulfillment_id', $part->id)->delete();
            }
            if ($parts->isEmpty()) {
                app(StockReservationService::class)->release($order);
            }
            $order->forceFill(['status' => OrderStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => $reason,
                'stock_released_at' => $order->stock_reserved_at ? now() : null])->save();
            app(BuyerInbox::class)->record($order, 'order:'.$order->id.':cancelled', 'Order cancelled', 'Your order was cancelled. No payment is due for this order.');
        }, 3);
    }
}
