@extends('layouts.portal')
@section('title', 'COD cash ledger · FarSell')
@section('content')
    <h1 class="text-2xl font-semibold">{{ $isAdmin ? $rider->user?->name.' · ' : '' }}COD cash ledger</h1>
    @include('finance.errors')
    <dl class="fs-card my-5 grid gap-4 p-5 sm:grid-cols-3">
        @foreach(['collected_cents' => 'Collected', 'remitted_cents' => 'Remitted', 'owed_cents' => 'Still owed'] as $key => $label)
            <div><dt class="text-sm text-text-muted">{{ $label }}</dt><dd class="text-xl font-semibold">PHP {{ \App\Support\Money::decimal($balance[$key]) }}</dd></div>
        @endforeach
    </dl>
    <p class="mb-4 text-text-muted">Delivery earnings are a separate ledger and are not deducted from COD cash owed.</p>
    @if($isAdmin)
        <form action="{{ route('admin.remittances.store', $rider) }}" method="post" class="fs-card mb-5 space-y-3 p-5">@csrf
            <h2 class="font-semibold">Record cash received</h2>
            <label class="block">Amount (PHP)<input class="fs-input mt-1 w-full" name="amount" inputmode="decimal" required value="{{ old('amount') }}"></label>
            <label class="block">Received date and time ({{ config('app.timezone') }})<input class="fs-input mt-1 w-full" name="received_at" type="datetime-local" required value="{{ old('received_at') }}"></label>
            <label class="block">Note<textarea class="fs-input mt-1 w-full" name="note" required maxlength="1000">{{ old('note') }}</textarea></label>
            <button type="submit" class="rounded-lg bg-accent px-4 py-2 text-white">Record remittance</button>
        </form>
    @endif
    @forelse($remittances as $remittance)
        <article class="fs-card mb-4 p-5">
            <h2 class="font-semibold">PHP {{ \App\Support\Money::decimal($remittance->amount_cents) }} · {{ $remittance->voided_at ? 'Voided' : 'Received' }}</h2>
            <p class="mt-2">{{ $remittance->received_at->format('Y-m-d H:i') }} · {{ $remittance->note }}</p>
            @if($remittance->voided_at)<p class="mt-2">Void reason: {{ $remittance->void_reason }}</p>
            @elseif($isAdmin)
                <form method="post" action="{{ route('admin.remittances.void', $remittance) }}" class="mt-4 space-y-2">@csrf
                    <label class="block">Void reason<textarea class="fs-input mt-1 w-full" name="void_reason" required maxlength="1000"></textarea></label>
                    <button type="submit" class="rounded-lg border px-4 py-2">Void remittance</button>
                </form>
            @endif
        </article>
    @empty<p>No remittances recorded.</p>@endforelse
    {{ $remittances->links() }}
@endsection
