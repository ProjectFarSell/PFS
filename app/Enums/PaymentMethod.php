<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case GatewayStub = 'gateway_stub';
}
