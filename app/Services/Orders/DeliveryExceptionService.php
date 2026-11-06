<?php

namespace App\Services\Orders;

use App\Enums\FulfillmentStatus as Status;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeliveryExceptionService
{
    public const REASONS = ['unreachable', 'refused', 'buyer_wrong_variant', 'seller_wrong_item', 'address_issue', 'other'];

    /** Invoked within FulfillmentService's parent-order/fulfillment transaction. */
    public function transition(Order $order, Fulfillment $part, User $actor, string $action, array $data): array
    {
        if ($action === 'fail') {
            abort_unless($actor->role === UserRole::Rider && $part->rider?->user_id === $actor->id && $part->rider->isApproved(), 403);
            $this->expect($part, Status::InTransit);
            if ($part->retry_at?->isFuture() || $part->cod_collected_at) {
                $this->invalid('A scheduled retry is not due, or payment has already been collected. Contact admin.');
            }
            $attempt = $part->attempts()->create([
                'number' => $part->attempts()->count() + 1, 'actor_id' => $actor->id,
                'reason' => $data['failure_reason'], 'contact_method' => $data['contact_method'], 'note' => $data['exception_note'],
            ]);

            return [Status::DeliveryFailed, 'Attempt '.$attempt->number.': '.str_replace('_', ' ', $attempt->reason).'. '.$attempt->note];
        }
        if ($action === 'retry') {
            abort_unless($actor->role === UserRole::Admin, 403);
            $this->expect($part, Status::DeliveryFailed);
            if ($part->attempts()->count() >= config('marketplace.max_delivery_attempts') || ! $part->rider?->isApproved()) {
                $this->invalid('Retry limit reached or rider unavailable. Arrange return to seller.');
            }
            $part->retry_at = $data['retry_at'];

            return [Status::InTransit, 'Admin scheduled another attempt for '.$part->retry_at->toIso8601String().'. '.$data['exception_note']];
        }
        if ($action === 'return_to_seller') {
            abort_unless($actor->role === UserRole::Admin, 403);
            $this->expect($part, Status::DeliveryFailed);
            // There is no real prepaid gateway yet; preserve a manual refund review if one is added later.
            if ($order->payment_method !== PaymentMethod::Cod || $part->cod_collected_at) {
                $this->invalid('This flow requires uncollected COD. Resolve payment separately before returning.');
            }
            $part->retry_at = null;

            return [Status::Returning, 'Admin instructed return to seller. '.$data['exception_note']];
        }
        abort_unless($actor->role === UserRole::Seller && $part->shop?->user_id === $actor->id, 403);
        $this->expect($part, Status::Returning);
        if (! filter_var($data['return_received'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->invalid('Confirm physical receipt of these returned items.');
        }
        $part->returned_at = now();
        $part->return_disposition = $data['return_disposition'];
        if (! $part->stock_released_at) {
            if ($order->stock_reserved_at && ! $order->stock_released_at && $part->return_disposition === 'restock') {
                app(StockReservationService::class)->releaseItems($part->items()->orderBy('product_id')->get());
            }
            // Reservation is resolved even when goods are held out of sellable inventory.
            $part->stock_released_at = now();
        }

        return [Status::Returned, 'Seller confirmed returned goods; '.$part->return_disposition.'. '.$data['exception_note']];
    }

    private function expect(Fulfillment $part, Status $status): void
    {
        if ($part->status !== $status) {
            $this->invalid('Shipment changed or action unavailable. Refresh and try again.');
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['fulfillment' => $message]);
    }
}
