<?php

namespace App\Services\Checkout;

use App\Contracts\DeliveryFeeService;
use App\Models\Address;
use App\Services\Orders\MarketplaceSettings;
use App\Support\Money;
use Illuminate\Support\Collection;

class FlatRateDeliveryFeeService implements DeliveryFeeService
{
    public function calculate(Address $address, Collection $lines): float
    {
        // Preserve the legacy float interface only at the boundary; no float math.
        return (float) Money::decimal(app(MarketplaceSettings::class)->current()->delivery_fee_cents);
    }
}
