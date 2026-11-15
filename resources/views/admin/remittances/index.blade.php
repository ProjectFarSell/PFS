@extends('layouts.portal')
@section('title', 'Rider COD remittances · FarSell')
@section('content')
    <h1 class="text-2xl font-semibold">Rider COD remittances</h1>
    <p class="my-3 text-text-muted">Cash collected and handed back is separate from rider delivery earnings.</p>
    @forelse($riders as $rider)
        <article class="fs-card mb-4 p-5">
            <h2 class="font-semibold"><a class="underline" href="{{ route('admin.remittances.show', $rider) }}">{{ $rider->user?->name ?? 'Rider' }}</a></h2>
            <p class="mt-2">Still owed: PHP {{ \App\Support\Money::decimal($breakdowns[$rider->id]['owed_cents']) }}</p>
        </article>
    @empty<p>No riders to show.</p>@endforelse
    {{ $riders->links() }}
@endsection
