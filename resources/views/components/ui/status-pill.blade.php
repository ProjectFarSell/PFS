@props(['status'])

@php
    $tone = match ($status) {
        'delivered', 'completed', 'approved', 'active', 'sent', 'available' => 'badge-success',
        'cancelled', 'rejected', 'refunded', 'suspended', 'failed', 'delivery_failed' => 'badge-error',
        'processing', 'paid', 'packed', 'confirmed', 'accepted', 'ready', 'assigned', 'in_transit', 'shipped', 'sending', 'retrying' => 'badge-accent',
        default => 'badge-neutral',
    };
@endphp

<span {{ $attributes->class(['badge', $tone]) }}>{{ $slot->isEmpty() ? str($status)->replace('_', ' ')->ucfirst() : $slot }}</span>
