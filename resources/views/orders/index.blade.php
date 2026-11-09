@extends('layouts.app')

@section('title', 'My Orders · FarSell')

@section('content')
    <a href="{{ route('returns.index') }}" class="mb-4 inline-block text-sm text-accent underline">My returns & refunds</a>
    <a href="{{ route('replacements.index') }}" class="mb-4 ml-3 inline-block text-sm text-accent underline">My replacement requests</a>
    <a href="{{ route('account.profile') }}" class="inline-block text-sm text-accent hover:text-accent mb-3"><x-ui.icon name="previous" size="sm" class="inline-block align-text-bottom" /> My Profile</a>
    <h1 class="text-lg font-semibold mb-1">My Orders</h1>
    <p class="text-sm text-text-muted mb-4">View your purchases and their current status.</p>

    <div class="space-y-3">
        @forelse ($orders as $order)
            <article class="rounded-xl border border-surface-border bg-surface p-4">
                <div class="flex flex-wrap justify-between items-start gap-2">
                    <div>
                        <h2 class="font-semibold text-sm">Order {{ $order->number }}</h2>
                        <p class="text-xs text-text-muted mt-1">
                            <time datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->format('M j, Y · g:i A') }}</time>
                        </p>
                    </div>
                    <span class="rounded-full bg-surface-muted px-3 py-1 text-xs font-medium">{{ $order->status->label() }}</span>
                </div>
                <div class="mt-3 flex flex-wrap justify-between items-center gap-3">
                    <div>
                        <p class="font-semibold text-accent">₱{{ number_format((float) $order->total, 2) }}</p>
                        <p class="text-xs text-text-muted mt-1">
                            {{ (int) $order->items_sum_qty }} {{ (int) $order->items_sum_qty === 1 ? 'item' : 'items' }}
                            · {{ $order->payment_method === \App\Enums\PaymentMethod::Cod ? 'Cash on delivery' : 'Card / e-wallet (demo)' }}
                        </p>
                    </div>
                    <x-ui.button variant="link" :href="route('orders.show', $order)" aria-label="View order {{ $order->number }}"
                       >View details</x-ui.button>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-surface-border bg-surface px-4 py-8 text-center">
                <p class="font-medium">You haven't placed any orders yet.</p>
                <x-ui.button :href="route('home')" variant="primary" size="lg" class="mt-3">Browse products</x-ui.button>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
@endsection
