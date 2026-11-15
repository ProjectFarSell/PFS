<?php

namespace App\Enums;

enum HandlingFlag: string
{
    case Fragile = 'fragile';
    case Perishable = 'perishable';
    case Liquid = 'liquid';
    case KeepUpright = 'keep_upright';
    case KeepDry = 'keep_dry';
    case KeepCold = 'keep_cold';
    case Flammable = 'flammable';
    case Heavy = 'heavy';

    public function label(): string
    {
        return match ($this) {
            self::Fragile => 'Fragile', self::Perishable => 'Perishable', self::Liquid => 'Liquid',
            self::KeepUpright => 'Keep upright', self::KeepDry => 'Keep dry', self::KeepCold => 'Keep cold',
            self::Flammable => 'Flammable', self::Heavy => 'Heavy',
        };
    }
}
