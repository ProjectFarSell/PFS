<?php

namespace App\Services\Orders;

use App\Enums\FulfillmentStatus as Status;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FulfillmentService
{
    /** Called inside checkout's transaction, after order items are saved. */
    public function createForOrder(Order $order): void
    {
        $groups = $order->items()->with('product.shop')->orderBy('id')->get()->groupBy('product.shop_id');
        $fee = (int) round((float) $order->shipping_fee * 100);
        $index = 0;
        foreach ($groups as $shopId => $items) {
            $fulfillment = $order->fulfillments()->create([
                'shop_id' => $shopId,
                'shop_name' => $items->first()->product->shop->name,
                'status' => Status::Pending,
                'subtotal' => $items->sum('line_total'),
                'shipping_fee' => (intdiv($fee, $groups->count()) + ($index++ < $fee % $groups->count() ? 1 : 0)) / 100,
            ]);
            $order->items()->whereIn('id', $items->pluck('id'))->update(['fulfillment_id' => $fulfillment->id]);
            $fulfillment->events()->create(['actor_id' => $order->user_id, 'status' => Status::Pending->value, 'note' => 'Order placed.']);
        }
    }

    public function act(Fulfillment $fulfillment, User $actor, string $action, array $data): void
    {
        DB::transaction(function () use ($fulfillment, $actor, $action, $data) {
            // All mutations lock the parent first to serialize sibling updates and stock releases.
            $order = Order::query()->lockForUpdate()->findOrFail($fulfillment->order_id);
            $part = Fulfillment::query()->lockForUpdate()->findOrFail($fulfillment->id);
            $actor = User::query()->findOrFail($actor->id);
            $note = null;

            if (in_array($action, ['accept', 'reject', 'ready', 'request_rider'], true)) {
                abort_unless($actor->role === UserRole::Seller && $part->shop()->where('user_id', $actor->id)->exists(), 403);
                $this->expect($part, match ($action) {
                    'ready' => Status::Accepted,
                    'request_rider' => Status::Ready,
                    default => Status::Pending,
                });
                if ($action === 'request_rider' && ($part->pickup_city !== null || $part->rider_id !== null)) {
                    throw ValidationException::withMessages(['fulfillment' => 'A rider request has already been published for this shipment.']);
                }
                $next = match ($action) {
                    'accept' => Status::Accepted, 'reject' => Status::Rejected, 'ready', 'request_rider' => Status::Ready
                };
                if (in_array($action, ['ready', 'request_rider'], true)) {
                    $part->pickup_address = $data['pickup_address'];
                    $part->pickup_contact = $data['pickup_contact'];
                    $part->pickup_city = DeliveryRequestService::city($part->shop->city);
                    $note = 'Rider request published for '.$part->shop->city.'.';
                }
                if ($action === 'reject') {
                    $note = $data['reason'];
                    $part->rejection_reason = $note;
                    if ($order->stock_reserved_at && ! $order->stock_released_at && ! $part->stock_released_at) {
                        foreach ($part->items()->orderBy('product_id')->get() as $item) {
                            if ($item->product_id) {
                                Product::query()->whereKey($item->product_id)->increment('stock', $item->qty);
                            }
                        }
                        $part->stock_released_at = now();
                    }
                }
            } elseif (in_array($action, ['claim', 'decline'], true)) {
                abort_unless($actor->role === UserRole::Rider, 403);
                $rider = RiderProfile::query()->where('user_id', $actor->id)->lockForUpdate()->first();
                abort_unless($rider?->isApproved() && $rider->is_available, 403);
                $this->expect($part, Status::Ready);
                abort_unless($part->rider_id === null && $part->pickup_city !== null
                    && $part->pickup_city === DeliveryRequestService::city($rider->city), 403);
                $offer = DB::table('rider_delivery_requests')->where('fulfillment_id', $part->id)
                    ->where('rider_id', $rider->id)->whereNull('declined_at')->where('expires_at', '>', now())->first();
                if (! $offer) {
                    throw ValidationException::withMessages(['fulfillment' => 'This delivery offer is no longer available to you. Refresh your dashboard.']);
                }
                if ($action === 'decline') {
                    DB::table('rider_delivery_requests')->where('id', $offer->id)->update(['declined_at' => now(), 'updated_at' => now()]);
                    app(DeliveryRequestService::class)->notifyRiders($part);

                    return;
                }
                $part->rider_id = $rider->id;
                $next = Status::Assigned;
                $note = 'Delivery request accepted by '.$actor->name;
            } elseif ($action === 'assign') {
                abort_unless($actor->role === UserRole::Admin, 403);
                $this->expect($part, Status::Ready);
                $rider = RiderProfile::query()->lockForUpdate()->find($data['rider_id']);
                if (! $rider || ! $rider->isApproved() || $rider->user?->role !== UserRole::Rider) {
                    throw ValidationException::withMessages(['rider_id' => 'Choose an approved rider with an active rider account.']);
                }
                $part->rider_id = $rider->id;
                $next = Status::Assigned;
                $note = 'Rider assigned: '.$rider->user->name;
            } elseif (in_array($action, ['pickup', 'deliver'], true)) {
                $rider = RiderProfile::query()->lockForUpdate()->find($part->rider_id);
                abort_unless($actor->role === UserRole::Rider && $rider?->user_id === $actor->id && $rider->isApproved(), 403);
                $this->expect($part, $action === 'pickup' ? Status::Assigned : Status::InTransit);
                $next = $action === 'pickup' ? Status::InTransit : Status::Delivered;
                if ($action === 'deliver') {
                    if ($order->payment_method === PaymentMethod::Cod && ! filter_var($data['cod_collected'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        throw ValidationException::withMessages(['cod_collected' => 'Confirm that the exact COD amount was collected before recording delivery.']);
                    }
                    $note = $data['delivery_note'];
                    $part->delivery_note = $note;
                    if ($order->payment_method === PaymentMethod::Cod) {
                        $part->cod_collected_at = now();
                    }
                }
            } else {
                abort_unless($action === 'complete' && $order->user_id === $actor->id, 403);
                $this->expect($part, Status::Delivered);
                $next = Status::Completed;
                $note = 'Buyer confirmed receipt.';
            }

            $part->status = $next;
            $part->save();
            if ($next === Status::Ready) {
                app(DeliveryRequestService::class)->notifyRiders($part);
            }
            $part->events()->create(['actor_id' => $actor->id, 'status' => $next->value, 'note' => $note]);
            $this->syncOrder($order);
        }, 3);
    }

    private function expect(Fulfillment $part, Status $expected): void
    {
        if ($part->status !== $expected) {
            throw ValidationException::withMessages(['fulfillment' => 'This shipment has changed or the action is unavailable. Refresh and try again.']);
        }
    }

    private function syncOrder(Order $order): void
    {
        $parts = $order->fulfillments()->get();
        $active = $parts->reject(fn ($part) => $part->status === Status::Rejected);
        $stages = [Status::Pending, Status::Accepted, Status::Ready, Status::Assigned, Status::InTransit, Status::Delivered, Status::Completed];
        $earliest = $active->sortBy(fn ($part) => array_search($part->status, $stages, true))->first()?->status;
        $order->status = match ($earliest) {
            null => OrderStatus::Cancelled,
            Status::Pending => $order->payment_method === PaymentMethod::Cod ? OrderStatus::PendingPayment : OrderStatus::Paid,
            Status::Accepted => OrderStatus::Confirmed,
            Status::Ready => OrderStatus::Packed,
            Status::Assigned => OrderStatus::Assigned,
            Status::InTransit => OrderStatus::InTransit,
            Status::Delivered => OrderStatus::Delivered,
            Status::Completed => OrderStatus::Completed,
            default => $order->status,
        };
        if ($active->isEmpty() && $order->stock_reserved_at) {
            $order->stock_released_at = now();
        }
        $order->save();
    }
}
