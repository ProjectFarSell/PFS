@extends('layouts.portal')

@section('title', 'Payouts needing review | FarSell')

@section('content')
    <x-ui.button variant="link" :href="route('admin.dashboard')" class="text-sm text-accent underline">Back to Admin Dashboard</x-ui.button>
    <section class="fs-card mt-4 p-5">
        <h1 class="text-xl font-semibold">Payouts needing review</h1>
        <p class="mt-2 text-sm text-text-muted">Settled shipments without a recorded delivery event need review before they can be eligible for payout.</p>
        <div class="mt-4 divide-y divide-surface-border">
            @forelse($settlements as $settlement)
                <article class="py-4">
                    <h2 class="font-semibold">Order {{ $settlement->fulfillment->order->number }}</h2>
                    <p class="text-sm text-text-muted">{{ $settlement->fulfillment->shop_name }} · Shipment #{{ $settlement->fulfillment->id }}</p>
                    <p class="text-sm text-text-muted">No delivery event recorded</p>
                </article>
            @empty
                <p class="py-6 text-sm text-text-muted">No settled shipments need review.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $settlements->links() }}</div>
    </section>
@endsection
