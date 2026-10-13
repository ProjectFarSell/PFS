<?php

namespace App\Services\Delivery;

use App\Contracts\DeliveryEtaService;
use App\Models\Address;

class CityBasedDeliveryEtaService implements DeliveryEtaService
{
    public function estimate(Address $buyerAddress, int $shopCityId): ?int
    {
        $buyerCityId = $buyerAddress->psgc_city_municipality_id;

        if ($buyerCityId === null) {
            return null;
        }

        if ($buyerCityId === $shopCityId) {
            return 4; // same city: up to 4 hours
        }

        return 24; // different city: next-day
    }
}
