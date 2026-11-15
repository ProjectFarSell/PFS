<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

class CartDisplay
{
    public function groups(Collection $lines): Collection
    {
        return $lines->groupBy(fn ($line) => $line->product->shop_id)->map(function ($shopLines) {
            $subtotal = BigDecimal::zero();
            foreach ($shopLines as $line) {
                $price = $line->variant?->price_override ?? $line->product->price;
                $subtotal = $subtotal->plus(BigDecimal::of((string) $price)->multipliedBy($line->qty));
            }

            return (object) ['shop' => $shopLines->first()->product->shop, 'lines' => $shopLines, 'subtotal' => (string) $subtotal->toScale(2)];
        })->values();
    }
}
