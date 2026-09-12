<?php

namespace App\Contracts;

use App\Models\Address;
use Illuminate\Support\Collection;

interface DeliveryFeeService
{
    /**
     * @param  Collection<int, object>  $lines
     */
    public function calculate(Address $address, Collection $lines): float;
}
