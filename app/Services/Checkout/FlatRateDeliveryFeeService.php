<?php

namespace App\Services\Checkout;

use App\Contracts\DeliveryFeeService;
use App\Models\Address;
use Illuminate\Support\Collection;

class FlatRateDeliveryFeeService implements DeliveryFeeService
{
    public function __construct(private readonly float $fee = 49.00) {}

    public function calculate(Address $address, Collection $lines): float
    {
        return $this->fee;
    }
}
