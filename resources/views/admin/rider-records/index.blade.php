@extends('layouts.portal')

@section('title', 'Rider records · FarSell')

@section('content')
    <a href="{{ route('admin.dashboard') }}" class="text-sm text-accent underline">Back to dashboard</a>
    <section class="fs-card-raised mt-4 p-5">
        <h1 class="text-2xl font-semibold">Rider records</h1>
        <p class="mt-2 text-sm text-text-muted">Review delivery status history, completed-shipment earnings, and buyer feedback.</p>
        <nav class="mt-4 flex flex-wrap gap-2" aria-label="Rider record sections">
            @foreach(['history' => 'Delivery history', 'earnings' => 'Earnings', 'feedback' => 'Buyer feedback'] as $key => $label)
                <a href="{{ route('admin.rider-records.index', ['section' => $key]) }}" @class(['btn-accent' => $section === $key, 'btn-outline' => $section !== $key]) @if($section === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </section>

    <section class="mt-5 overflow-x-auto rounded-xl border border-surface-border bg-surface">
        @if($section === 'history')
            <table class="w-full min-w-[50rem] text-left text-sm">
                <thead><tr class="border-b border-surface-border text-text-muted"><th class="p-3">Time</th><th class="p-3">Order / shipment</th><th class="p-3">Rider</th><th class="p-3">Status</th><th class="p-3">Updated by</th><th class="p-3">Note</th></tr></thead>
                <tbody>
                    @forelse($records as $event)
                        <tr class="border-b border-surface-border last:border-0">
                            <td class="p-3 whitespace-nowrap"><time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('M j, Y g:i A') }}</time></td>
                            <td class="p-3">{{ $event->fulfillment?->order?->number ?? 'Unavailable' }} / #{{ $event->fulfillment_id }}</td>
                            <td class="p-3">{{ $event->fulfillment?->rider?->user?->name ?? 'Not assigned' }}</td>
                            <td class="p-3">{{ \App\Enums\FulfillmentStatus::tryFrom($event->status)?->label() ?? str($event->status)->headline() }}</td>
                            <td class="p-3">{{ $event->actor?->name ?? 'System / unavailable' }}</td>
                            <td class="p-3">{{ $event->note ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6 text-center text-text-muted">No rider delivery history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif($section === 'earnings')
            <table class="w-full min-w-[42rem] text-left text-sm">
                <thead><tr class="border-b border-surface-border text-text-muted"><th class="p-3">Earned at</th><th class="p-3">Rider</th><th class="p-3">Order / shipment</th><th class="p-3 text-right">Amount</th></tr></thead>
                <tbody>
                    @forelse($records as $earning)
                        <tr class="border-b border-surface-border last:border-0">
                            <td class="p-3 whitespace-nowrap">{{ $earning->earned_at->format('M j, Y g:i A') }}</td>
                            <td class="p-3">{{ $earning->riderProfile?->user?->name ?? 'Unavailable' }}</td>
                            <td class="p-3">{{ $earning->fulfillment?->order?->number ?? 'Unavailable' }} / #{{ $earning->fulfillment_id }}</td>
                            <td class="p-3 text-right font-semibold">₱{{ number_format((float) $earning->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-6 text-center text-text-muted">No rider earnings have been recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <table class="w-full min-w-[46rem] text-left text-sm">
                <thead><tr class="border-b border-surface-border text-text-muted"><th class="p-3">Submitted</th><th class="p-3">Buyer</th><th class="p-3">Rider</th><th class="p-3">Order</th><th class="p-3">Rating</th><th class="p-3">Feedback</th></tr></thead>
                <tbody>
                    @forelse($records as $rating)
                        <tr class="border-b border-surface-border last:border-0">
                            <td class="p-3 whitespace-nowrap">{{ $rating->created_at->format('M j, Y g:i A') }}</td>
                            <td class="p-3">{{ $rating->buyer?->name ?? 'Unavailable' }}</td>
                            <td class="p-3">{{ $rating->riderProfile?->user?->name ?? 'Unavailable' }}</td>
                            <td class="p-3">{{ $rating->fulfillment?->order?->number ?? 'Unavailable' }}</td>
                            <td class="p-3 whitespace-nowrap">{{ $rating->rating }} / 5</td>
                            <td class="p-3">{{ $rating->feedback ?: 'No written feedback' }}@if($rating->tags)<p class="mt-1 text-xs text-text-muted">{{ implode(' · ', $rating->tags) }}</p>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6 text-center text-text-muted">No buyer feedback yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </section>
    <div class="mt-4">{{ $records->links() }}</div>
@endsection
