@extends('layouts.portal')
@section('title', 'Payout history · FarSell')
@section('content')
    <h1 class="text-2xl font-semibold">{{ $shop->name }} · Payout history</h1>
    <p class="my-3 text-text-muted">Credits are held for {{ $holdDays }} days after recorded delivery and while a return is open.</p>
    @include('finance.errors')
    <section class="fs-card mb-5 p-5">@include('payouts.balance')</section>
    @if($isAdmin)
        <form class="mb-5" action="{{ route('admin.payouts.store', $shop) }}" method="post">@csrf
            <button class="rounded-lg bg-accent px-4 py-3 text-white" type="submit">Create eligible payout batch</button>
        </form>
    @endif
    @forelse($payouts as $payout)
        <article class="fs-card mb-5 p-5">
            <h2 class="font-semibold">Payout #{{ $payout->id }} · {{ ucfirst($payout->status) }} · PHP {{ \App\Support\Money::decimal($payout->amount_cents) }}</h2>
            <p class="mt-2 text-sm">Created {{ $payout->created_at->format('Y-m-d H:i') }}</p>
            @if($payout->status === 'paid')
                <p class="mt-2">Payment reference: {{ $payout->payment_reference }} · Paid {{ $payout->paid_at->format('Y-m-d H:i') }}</p>
            @elseif($payout->status === 'cancelled')
                <p class="mt-2">Cancelled {{ $payout->cancelled_at->format('Y-m-d H:i') }} · {{ $payout->cancellation_reason }}</p>
            @elseif($isAdmin)
                <div class="mt-4 grid gap-5 md:grid-cols-2">
                    <form action="{{ route('admin.payouts.pay', $payout) }}" method="post" class="space-y-3">@csrf
                        <h3 class="font-semibold">Record completed payment</h3>
                        <label class="block">Payment reference<input class="fs-input mt-1 w-full" name="payment_reference" required maxlength="120" value="{{ old('payment_reference') }}"></label>
                        <label class="block">Payment date and time ({{ config('app.timezone') }})<input class="fs-input mt-1 w-full" type="datetime-local" name="paid_at" required value="{{ old('paid_at') }}"></label>
                        <p class="text-sm text-text-muted">Only record money already paid. This action does not transfer funds.</p>
                        <button class="rounded-lg bg-accent px-4 py-2 text-white" type="submit">Mark paid</button>
                    </form>
                    <form action="{{ route('admin.payouts.cancel', $payout) }}" method="post" class="space-y-3">@csrf
                        <h3 class="font-semibold">Cancel unpaid batch</h3>
                        <label class="block">Reason<textarea class="fs-input mt-1 w-full" name="cancellation_reason" required maxlength="1000">{{ old('cancellation_reason') }}</textarea></label>
                        <button class="rounded-lg border px-4 py-2" type="submit">Cancel batch</button>
                    </form>
                </div>
            @endif
        </article>
    @empty
        <p>No payout batches have been recorded.</p>
    @endforelse
    {{ $payouts->links() }}
@endsection
