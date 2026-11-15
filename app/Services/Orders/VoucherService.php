<?php

namespace App\Services\Orders;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    /** The row lock serializes usage-limit checks and redemption across both clients. */
    public function quote(?string $code, User $buyer, Collection $lines, int $shipping, bool $lock = false): ?array
    {
        $code = strtoupper(trim($code ?? ''));
        if ($code === '') {
            return null;
        }
        if ($buyer->role !== UserRole::Buyer || $buyer->suspended_at) {
            $this->invalid('Vouchers are available to active buyer accounts.');
        }
        $voucher = Voucher::where('code', $code)->when($lock, fn ($query) => $query->lockForUpdate())->first();
        if (! $voucher || ! $voucher->is_active || $voucher->starts_at?->isFuture() || $voucher->expires_at?->lte(now())) {
            $this->invalid('This voucher is invalid, inactive, or outside its validity dates.');
        }
        $eligible = $lines->filter(fn ($line) => $voucher->shop_id === null || (int) $line->product->shop_id === (int) $voucher->shop_id);
        $subtotal = (int) $eligible->sum(fn ($line) => Money::cents((string) $line->unit_price) * (int) $line->qty);
        if ($subtotal <= 0) {
            $this->invalid('This shop voucher does not apply to the items in your checkout.');
        }
        if ($subtotal < $voucher->minimum_spend_cents) {
            $this->invalid('Your merchandise total does not meet this voucher’s minimum spend.');
        }
        $uses = DB::table('voucher_redemptions')->where('voucher_id', $voucher->id);
        if ($lock) {
            // A mobile replay transaction may already have a REPEATABLE READ snapshot.
            // Locking reads see the latest committed redemptions after the voucher lock;
            // ordinary COUNT queries could otherwise reuse an older snapshot.
            $redemptions = $uses->lockForUpdate()->get(['user_id']);
            $totalUses = $redemptions->count();
            $buyerUses = $redemptions->where('user_id', $buyer->id)->count();
        } else {
            $totalUses = (clone $uses)->count();
            $buyerUses = $uses->where('user_id', $buyer->id)->count();
        }
        if (($voucher->usage_limit !== null && $totalUses >= $voucher->usage_limit)
            || $buyerUses >= $voucher->per_buyer_limit) {
            $this->invalid('This voucher’s usage limit has been reached.');
        }
        $amount = match ($voucher->type) {
            'fixed' => min($subtotal, $voucher->value),
            'percentage' => min($subtotal, intdiv($subtotal * $voucher->value, 10000)),
            'free_shipping' => $voucher->shop_id === null ? $shipping : 0,
            default => 0,
        };
        if ($voucher->maximum_discount_cents !== null) {
            $amount = min($amount, $voucher->maximum_discount_cents);
        }
        if ($amount <= 0) {
            $this->invalid('This voucher does not give a discount for this checkout.');
        }

        return ['id' => $voucher->id, 'code' => $voucher->code, 'name' => $voucher->name,
            'type' => $voucher->type, 'version' => $voucher->version, 'funded_by' => $voucher->shop_id === null ? 'farsell' : 'seller', 'shop_id' => $voucher->shop_id,
            'discount_cents' => $voucher->type === 'free_shipping' ? 0 : $amount,
            'shipping_discount_cents' => $voucher->type === 'free_shipping' ? $amount : 0];
    }

    public function redeem(Order $order, ?array $quote): void
    {
        if ($quote === null) {
            return;
        }
        DB::table('voucher_redemptions')->insert(['voucher_id' => $quote['id'], 'user_id' => $order->user_id,
            'order_id' => $order->id, 'discount_cents' => $quote['discount_cents'],
            'shipping_discount_cents' => $quote['shipping_discount_cents'], 'created_at' => now()]);
    }

    /** Exact proportional allocation, with remainder cents assigned in stable line order. */
    public function allocate(int $discount, array $weights): array
    {
        $total = array_sum($weights);
        if ($discount < 0 || $discount > $total) {
            throw new \LogicException('Discount exceeds its allocation basis.');
        }
        $parts = array_map(fn ($weight) => $total > 0 ? BigInteger::of($discount)->multipliedBy($weight)->quotient($total)->toInt() : 0, $weights);
        $remaining = $discount - array_sum($parts);
        foreach ($weights as $key => $weight) {
            if ($remaining > 0 && $parts[$key] < $weight) {
                $parts[$key]++;
                $remaining--;
            }
        }

        return $parts;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['voucher_code' => $message]);
    }
}
