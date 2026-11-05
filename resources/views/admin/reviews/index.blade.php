@extends('layouts.portal')

@section('title', 'Buyer reviews · FarSell')

@section('content')
    <section class="fs-card-raised p-5">
        <h1 class="text-2xl font-semibold">Buyer reviews</h1>
        <p class="mt-2 text-sm text-text-muted">Review verified purchase feedback, attachments, and service ratings.</p>
        <nav class="mt-4 flex flex-wrap gap-2" aria-label="Review sections">
            <a href="{{ route('admin.reviews.index') }}" @class(['btn-accent' => $type === 'products', 'btn-outline' => $type !== 'products']) @if($type === 'products') aria-current="page" @endif>Product reviews</a>
            <a href="{{ route('admin.reviews.index', ['type' => 'service']) }}" @class(['btn-accent' => $type === 'service', 'btn-outline' => $type !== 'service']) @if($type === 'service') aria-current="page" @endif>Seller & delivery ratings</a>
            <a href="{{ route('admin.rider-records.index', ['section' => 'feedback']) }}" class="btn-outline">Rider feedback</a>
        </nav>
    </section>

    @if($type === 'products')
        <div class="mt-5 space-y-4">
            @forelse($records as $review)
                <article class="fs-card p-5">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div><h2 class="font-semibold">{{ $review->orderItem?->name ?? 'Unavailable product' }}</h2><p class="mt-1 text-sm text-text-muted">{{ $review->buyer?->name ?? 'Unavailable buyer' }} · {{ $review->is_anonymous ? 'Publicly anonymous' : 'Public name displayed' }}</p></div>
                        <p class="font-semibold text-amber-500">{{ $review->rating }} / 5</p>
                    </div>
                    @if($review->orderItem?->order)<a href="{{ route('orders.show', $review->orderItem->order_id) }}" class="mt-2 inline-block text-sm text-accent underline">Order {{ $review->orderItem->order->number }}</a>@endif
                    <p class="mt-1 text-xs text-text-muted">{{ $review->created_at->format('M j, Y g:i A') }}</p>
                    @if($review->feedback)<p class="mt-3 whitespace-pre-line break-words text-sm">{{ $review->feedback }}</p>@endif
                    <x-review-media :media="$review->media" />
                </article>
            @empty
                <p class="fs-card p-6 text-text-muted">No product reviews yet.</p>
            @endforelse
        </div>
    @else
        <div class="mt-5 overflow-x-auto rounded-xl border border-surface-border bg-surface">
            <table class="w-full min-w-[46rem] text-left text-sm">
                <thead><tr class="border-b border-surface-border text-text-muted"><th class="p-3">Buyer</th><th class="p-3">Order / shop</th><th class="p-3">Seller service</th><th class="p-3">Delivery speed</th><th class="p-3">Rider service</th><th class="p-3">Submitted</th></tr></thead>
                <tbody>
                    @forelse($records as $feedback)
                        <tr class="border-b border-surface-border last:border-0">
                            <td class="p-3">{{ $feedback->buyer?->name ?? 'Unavailable buyer' }}</td>
                            <td class="p-3">@if($feedback->fulfillment?->order)<a href="{{ route('orders.show', $feedback->fulfillment->order_id) }}" class="text-accent underline">{{ $feedback->fulfillment->order->number }}</a>@endif<p class="mt-1 text-text-muted">{{ $feedback->fulfillment?->shop_name }}</p></td>
                            <td class="p-3">{{ $feedback->seller_service_rating }} / 5</td>
                            <td class="p-3">{{ $feedback->delivery_speed_rating }} / 5</td>
                            <td class="p-3">{{ $feedback->fulfillment?->riderRating ? $feedback->fulfillment->riderRating->rating.' / 5' : 'Not rated' }}</td>
                            <td class="p-3">{{ $feedback->created_at->format('M j, Y g:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6 text-center text-text-muted">No service feedback yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
    <div class="mt-4">{{ $records->links() }}</div>
@endsection
