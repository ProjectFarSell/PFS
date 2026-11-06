<?php

namespace App\Services\Orders;

use App\Enums\FulfillmentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\ReplacementRequest;
use App\Models\User;
use App\Services\Notifications\BuyerInbox;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplacementService
{
    public const SESSION_KEY = 'farsell.replacement_request';

    public function eligible(OrderItem $item): bool
    {
        return $item->fulfillment?->status === FulfillmentStatus::Returned && ! $item->fulfillment->cod_collected_at
            && $item->order->payment_method->value === 'cod';
    }

    public function submit(OrderItem $item, User $buyer, array $data): ReplacementRequest
    {
        return DB::transaction(function () use ($item, $buyer, $data) {
            Order::whereKey($item->order_id)->lockForUpdate()->firstOrFail();
            $item = OrderItem::with(['order', 'fulfillment', 'product.shop'])->findOrFail($item->id);
            abort_unless($buyer->role === UserRole::Buyer && $item->order->user_id === $buyer->id, 403);
            if (! $this->eligible($item) || $item->replacementRequest()->exists() || $data['quantity'] > $item->qty) {
                $this->invalid();
            }
            $case = new ReplacementRequest(['order_item_id' => $item->id, 'product_variant_id' => $data['variant_id'] ?? null,
                'quantity' => $data['quantity'], 'note' => $data['note']]);
            $this->validateSelection($case, $item);
            $case->save();

            return $case;
        }, 3);
    }

    public function review(ReplacementRequest $case, User $actor, array $data): void
    {
        DB::transaction(function () use ($case, $actor, $data) {
            Order::whereKey($case->item->order_id)->lockForUpdate()->firstOrFail();
            $case = ReplacementRequest::with('item.fulfillment.shop')->lockForUpdate()->findOrFail($case->id);
            abort_unless($actor->role === UserRole::Admin || ($actor->role === UserRole::Seller && $case->item->fulfillment->shop?->user_id === $actor->id), 403);
            if ($case->status !== 'pending' || ! $this->eligible($case->item)) {
                $this->invalid();
            }
            if ($data['decision'] === 'approved') {
                $this->validateSelection($case, $case->item);
            }
            $case->forceFill(['status' => $data['decision'], 'review_note' => $data['review_note'], 'reviewed_at' => now(), 'reviewed_by' => $actor->id])->save();
            app(BuyerInbox::class)->record($case->item->order, 'replacement:'.$case->id.':review', 'Replacement request '.$case->status, 'Open replacement requests to review the decision. An approval does not reserve stock or place an order.');
        }, 3);
    }

    /** Must execute before checkout product locks; serializes concurrent replacement orders. */
    public function forCheckout(int $id, User $buyer, Collection $lines): ReplacementRequest
    {
        $original = ReplacementRequest::with('item')->findOrFail($id);
        Order::whereKey($original->item->order_id)->lockForUpdate()->firstOrFail();
        $case = ReplacementRequest::with('item.order', 'item.fulfillment', 'item.product.shop')->lockForUpdate()->findOrFail($id);
        abort_unless($case->item->order->user_id === $buyer->id && $buyer->role === UserRole::Buyer, 403);
        $line = $lines->first();
        if ($case->status !== 'approved' || ! $this->eligible($case->item) || $lines->count() !== 1
            || $line->product->id !== $case->item->product_id || $line->variant?->id !== $case->product_variant_id || $line->qty !== $case->quantity) {
            $this->invalid();
        }
        $this->validateSelection($case, $case->item);

        return $case;
    }

    private function validateSelection(ReplacementRequest $case, OrderItem $item): void
    {
        $product = $item->product;
        $variant = $case->product_variant_id ? ProductVariant::find($case->product_variant_id) : null;
        if (! $product?->is_active || ! $product->shop?->is_active
            || ($product->has_variants && (! $variant || ! $variant->is_active || $variant->product_id !== $product->id || $variant->stock < $case->quantity))
            || (! $product->has_variants && ($variant || $product->stock < $case->quantity))) {
            $this->invalid('The selected item/variant is unavailable. Contact the seller before proceeding.');
        }
    }

    private function invalid(string $message = 'This replacement is not available or has already been processed.'): never
    {
        throw ValidationException::withMessages(['replacement' => $message]);
    }
}
