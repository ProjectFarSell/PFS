@extends('layouts.portal')
@section('title', 'Seller payouts · FarSell')
@section('content')
    <h1 class="text-2xl font-semibold">Seller payouts</h1>
    <p class="my-3 text-text-muted">Review eligible balances and record payments made outside FarSell. Hold period: {{ $holdDays }} days.</p>
    <div class="mb-4 flex flex-wrap gap-4">
        <a class="underline text-accent" href="{{ route('admin.payouts.needs-review') }}">Missing delivery records</a>
        <a class="underline text-accent" href="{{ route('admin.remittances.index') }}">Rider COD remittances</a>
        <a class="underline text-accent" href="{{ route('admin.marketplace-settings.edit') }}">Marketplace fees</a>
    </div>
    @foreach($shops as $shop)
        @php($balance = $breakdowns[$shop->id])
        <article class="fs-card mb-4 p-5">
            <h2 class="font-semibold"><a class="underline" href="{{ route('admin.payouts.show', $shop) }}">{{ $shop->name }}</a></h2>
            <p>{{ $shop->owner?->name }}</p>
            @include('payouts.balance')
        </article>
    @endforeach
    @if($shops->isEmpty())<p>No seller shops to show.</p>@endif
    {{ $shops->links() }}
@endsection
