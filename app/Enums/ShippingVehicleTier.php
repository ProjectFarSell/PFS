<?php

namespace App\Enums;

enum ShippingVehicleTier: string
{
    case Motorcycle = 'motorcycle';
    case SedanMpv = 'sedan_mpv';
    case VanLightTruck = 'van_light_truck';

    public function label(): string
    {
        return match ($this) {
            self::Motorcycle => 'Motorcycle', self::SedanMpv => 'Sedan / MPV', self::VanLightTruck => 'Van / light truck',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Motorcycle => 1, self::SedanMpv => 2, self::VanLightTruck => 3
        };
    }
}
