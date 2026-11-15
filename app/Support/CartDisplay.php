<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

class CartDisplay
{
    public function subtotal(Collection $lines): string
    {
        $subtotal = BigDecimal::zero();
        foreach ($lines as $line) {
            $price = $line->variant?->price_override ?? $line->product->price;
            $subtotal = $subtotal->plus(BigDecimal::of((string) $price)->multipliedBy($line->qty));
        }

        return (string) $subtotal->toScale(2);
    }

    public function groups(Collection $lines): Collection
    {
        return $lines->groupBy(fn ($line) => $line->product->shop_id)->map(function ($shopLines) {
            return (object) ['shop' => $shopLines->first()->product->shop, 'lines' => $shopLines, 'subtotal' => $this->subtotal($shopLines)];
        })->values();
    }
}
