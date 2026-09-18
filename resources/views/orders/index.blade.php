@extends('layouts.app')

@section('title', 'My Orders · FarSell')

@section('content')
    <a href="{{ route('account.profile') }}" class="inline-block text-sm text-accent hover:text-accent mb-3">← My Profile</a>
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
                    <a href="{{ route('orders.show', $order) }}" aria-label="View order {{ $order->number }}"
                       class="rounded-full border border-violet-600 px-4 py-2 text-sm text-accent hover:bg-violet-50 transition-colors">View details</a>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-surface-border bg-surface px-4 py-8 text-center">
                <p class="font-medium">You haven't placed any orders yet.</p>
                <a href="{{ route('home') }}" class="inline-block mt-3 rounded-full bg-violet-600 hover:bg-violet-700 px-5 py-2 text-sm text-white transition-colors">Browse products</a>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
@endsection
