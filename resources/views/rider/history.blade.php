@extends('layouts.portal')

@section('title', 'Delivery history · FarSell')

@section('content')
    <a href="{{ route('rider.dashboard') }}" class="text-sm text-accent underline">Back to dashboard</a>
    <section class="fs-card-raised mt-4 p-5">
        <h1 class="text-2xl font-semibold">Delivery history</h1>
        <p class="mt-2 text-sm text-text-muted">Your assigned shipments and their status timestamps.</p>
    </section>
    <div class="mt-5 space-y-4">
        @forelse($deliveries as $delivery)
            <article class="fs-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold">{{ $delivery->order?->number ?? 'Order unavailable' }} · Shipment #{{ $delivery->id }}</h2>
                    <span class="badge badge-neutral">{{ $delivery->status->label() }}</span>
                </div>
                <p class="mt-1 text-sm text-text-muted">{{ $delivery->shop_name }}</p>
                <ol class="mt-4 space-y-3" aria-label="Shipment status history">
                    @foreach($delivery->events as $event)
                        <li class="border-l-2 border-accent/40 pl-3">
                            <p class="font-medium">{{ \App\Enums\FulfillmentStatus::tryFrom($event->status)?->label() ?? str($event->status)->headline() }}</p>
                            <time class="text-xs text-text-muted" datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('M j, Y g:i A') }}</time>
                            @if($event->note)<p class="mt-1 text-sm text-text-muted">{{ $event->note }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </article>
        @empty
            <p class="fs-card p-6 text-text-muted">No assigned delivery history yet.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $deliveries->links() }}</div>
@endsection
