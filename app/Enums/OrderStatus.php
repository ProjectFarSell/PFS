<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Packed = 'packed';
    case Assigned = 'assigned';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Awaiting seller confirmation',
            self::Paid => 'Demo paid / Awaiting seller confirmation',
            self::Confirmed => 'Preparing items',
            self::Completed => 'Completed',
            self::Packed => 'Ready for pickup',
            self::Assigned => 'Rider assigned',
            self::InTransit => 'Out for delivery',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::Cancelled],
            self::Paid => [self::Packed, self::Cancelled],
            self::Packed => [self::Assigned, self::Cancelled],
            self::Assigned => [self::InTransit, self::Cancelled],
            self::InTransit => [self::Delivered],
            self::Confirmed => [self::Packed, self::Cancelled],
            self::Delivered => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
