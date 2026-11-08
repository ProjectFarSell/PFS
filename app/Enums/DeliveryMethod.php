<?php

namespace App\Enums;

enum DeliveryMethod: string
{
    case Internal = 'internal';
    case Lalamove = 'lalamove';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'FarSell rider', self::Lalamove => 'Lalamove',
        };
    }
}
