<?php

namespace App\Services\Orders;

use App\Models\Address;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CheckoutAvailability
{
    public function ensureOpen(): void
    {
        abort_unless(config('release.checkout_enabled'), 503, 'Ordering is temporarily paused. Please try again later.');
    }

    public function ensureServiceable(Address $address, Collection $lines): void
    {
        $this->ensureOpen();
        if (! config('release.beta_enabled')) {
            return;
        }
        $cities = config('release.beta_city_ids', []);
        if (! in_array((int) $address->psgc_city_municipality_id, $cities, true) || $lines->contains(
            fn ($line) => ! in_array((int) $line->product->shop?->psgc_city_municipality_id, $cities, true)
        )) {
            throw ValidationException::withMessages(['address_id' => 'Delivery is currently available only within our supported beta cities. Please choose a supported delivery address.']);
        }
    }
}
