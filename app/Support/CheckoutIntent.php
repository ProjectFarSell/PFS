<?php

namespace App\Support;

class CheckoutIntent
{
    public static function active(): bool
    {
        $intended = session('url.intended');

        return is_string($intended) && strtok($intended, '?') === route('checkout.create');
    }
}
