<?php

namespace App\Enums;

enum FulfillmentStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Ready = 'ready';
    case Assigned = 'assigned';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case DeliveryFailed = 'delivery_failed';
    case Returning = 'returning';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting seller confirmation',
            self::Accepted => 'Preparing items',
            self::Ready => 'Ready for pickup',
            self::Assigned => 'Rider assigned',
            self::InTransit => 'Out for delivery',
            self::Delivered => 'Delivered — awaiting buyer confirmation',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected by seller',
            self::Cancelled => 'Cancelled by buyer',
            self::DeliveryFailed => 'Delivery attempt failed — awaiting review',
            self::Returning => 'Returning to seller',
            self::Returned => 'Returned to seller',
        };
    }
}
