@extends('layouts.portal')

@section('title', 'Rider earnings · FarSell')

@section('content')
    <a href="{{ route('rider.dashboard') }}" class="text-sm text-accent underline">Back to dashboard</a>
    <section class="fs-card-raised mt-4 p-5">
        <h1 class="text-2xl font-semibold">Earnings ledger</h1>
        <p class="mt-2 text-sm text-text-muted">Each completed shipment credits its full allocated delivery fee after the buyer confirms receipt.</p>
        <p class="mt-4 text-3xl font-bold">₱{{ number_format((float) $totalEarned, 2) }}</p>
        <p class="text-sm text-text-muted">Total recorded earnings</p>
    </section>

    <section class="mt-5 overflow-x-auto rounded-xl border border-surface-border bg-surface">
        <table class="w-full min-w-[38rem] text-left text-sm">
            <thead><tr class="border-b border-surface-border text-text-muted"><th class="p-3">Completed</th><th class="p-3">Order</th><th class="p-3">Shipment</th><th class="p-3 text-right">Earned</th></tr></thead>
            <tbody>
                @forelse($earnings as $earning)
                    <tr class="border-b border-surface-border last:border-0">
                        <td class="p-3"><time datetime="{{ $earning->earned_at->toIso8601String() }}">{{ $earning->earned_at->format('M j, Y g:i A') }}</time></td>
                        <td class="p-3">{{ $earning->fulfillment?->order?->number ?? 'Order unavailable' }}</td>
                        <td class="p-3">#{{ $earning->fulfillment_id }}</td>
                        <td class="p-3 text-right font-semibold">₱{{ number_format((float) $earning->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-6 text-center text-text-muted">No completed delivery earnings yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <div class="mt-4">{{ $earnings->links() }}</div>
@endsection
