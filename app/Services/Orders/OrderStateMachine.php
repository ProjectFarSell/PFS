<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use DomainException;
use Illuminate\Support\Facades\DB;

class OrderStateMachine
{
    public function __construct(private readonly StockReservationService $stock) {}

    public function transition(Order $order, OrderStatus $next): Order
    {
        return DB::transaction(function () use ($order, $next): Order {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $lockedOrder->status->canTransitionTo($next)) {
                throw new DomainException("Order cannot transition from {$lockedOrder->status->value} to {$next->value}.");
            }

            $lockedOrder->status = $next;
            $lockedOrder->save();

            if ($next === OrderStatus::Cancelled) {
                $this->stock->release($lockedOrder);
            }

            return $lockedOrder->refresh();
        });
    }
}
