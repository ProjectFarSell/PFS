<?php

namespace App\Services\Mobile;

use App\Models\MobileCart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Orders\PurchaseLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileCartService
{
    public function get(User $user): MobileCart
    {
        return MobileCart::firstOrCreate(['user_id' => $user->id], ['lines' => [], 'version' => 0]);
    }

    public function hydrate(MobileCart $cart): Collection
    {
        $products = Product::with(['shop', 'variants'])->whereIn('id', array_column($cart->lines, 'product_id'))->get()->keyBy('id');

        return collect($cart->lines)->map(function ($line) use ($products) {
            $product = $products->get($line['product_id']);
            $variant = $line['variant_id'] ? $product?->variants->firstWhere('id', $line['variant_id']) : null;
            $valid = $product && $product->is_active && $product->shop?->is_active && ($product->has_variants ? ($variant && $variant->is_active) : ! $line['variant_id']);
            $price = $valid ? MobileViews::cents($variant?->price_override ?? $product->price) : $line['price'];

            return (object) ['id' => $line['id'], 'product' => $product, 'variant' => $variant, 'variant_options' => $variant?->options ?: ($variant ? array_filter([$variant->option_name => $variant->option_value]) : []), 'qty' => $line['qty'], 'unit_price' => $price / 100, 'line_total' => $price * $line['qty'] / 100, 'valid' => (bool) $valid, 'stock' => $valid ? (int) ($variant?->stock ?? $product->stock) : 0, 'snapshot' => $line];
        })->values();
    }

    public function view(MobileCart $cart): array
    {
        $lines = $this->hydrate($cart);

        return ['version' => $cart->version, 'currency' => 'PHP', 'subtotal' => MobileViews::cents($lines->sum('line_total')), 'lines' => $lines->map(fn ($line) => ['id' => $line->id, 'product_id' => $line->product ? PublicIds::id($line->product) : $line->snapshot['product_public_id'], 'variant_id' => $line->variant ? PublicIds::id($line->variant) : $line->snapshot['variant_public_id'], 'name' => $line->product?->name ?? $line->snapshot['name'], 'sku' => $line->variant?->sku ?? $line->product?->sku ?? $line->snapshot['sku'], 'options' => (object) $line->variant_options, 'qty' => $line->qty, 'unit_price' => MobileViews::cents($line->unit_price), 'line_total' => MobileViews::cents($line->line_total), 'stock' => $line->stock, 'available' => $line->valid && $line->stock >= $line->qty])->all()];
    }

    public function version(MobileCart $cart, int $version): void
    {
        if ($cart->version !== $version) {
            throw new ApiFailure('cart_stale', 'Your cart changed. Refresh it before continuing.');
        }
    }

    public function add(MobileCart $cart, array $data): void
    {
        $product = PublicIds::find(Product::class, $data['product_id']);
        $variant = isset($data['variant_id']) ? PublicIds::find(ProductVariant::class, $data['variant_id']) : null;
        [$product, $variant, $stock] = app(PurchaseLine::class)->resolve($product->id, $variant?->id);
        $lines = $cart->lines;
        $key = $product->id.':'.($variant?->id ?? 0);
        $quantity = ($lines[$key]['qty'] ?? 0) + $data['qty'];
        if ($quantity > min(99, $stock) || (! isset($lines[$key]) && count($lines) >= 100)) {
            throw new ApiFailure('stock_conflict', 'This quantity is no longer available.');
        }
        $lines[$key] = ['id' => $lines[$key]['id'] ?? (string) Str::uuid(), 'product_id' => $product->id, 'variant_id' => $variant?->id, 'product_public_id' => PublicIds::id($product), 'variant_public_id' => PublicIds::id($variant), 'qty' => $quantity, 'name' => $product->name, 'sku' => $variant?->sku ?? $product->sku, 'price' => MobileViews::cents($variant?->price_override ?? $product->price)];
        $cart->update(['lines' => $lines, 'version' => $cart->version + 1]);
    }

    public function mutate(User $user, callable $work): array
    {
        return DB::transaction(function () use ($user, $work) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $cart = $this->get($user);
            $cart = MobileCart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $work($cart);

            return $this->view($cart);
        }, 3);
    }
}
