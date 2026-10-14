<?php

namespace App\Contracts;

use App\Models\Address;

interface DeliveryEtaService
{
    /**
     * Returns estimated delivery window in hours, or null if undeliverable/unknown.
     */
    public function estimate(Address $buyerAddress, int $shopCityId): ?int;
}
