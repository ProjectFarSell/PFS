@extends('layouts.portal')
@section('title', 'Marketplace fees · FarSell')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold">Marketplace fees</h1>
    @include('finance.errors')
    <form class="fs-card max-w-xl space-y-4 p-5" method="post" action="{{ route('admin.marketplace-settings.update') }}">@csrf @method('PUT')
        <input type="hidden" name="version" value="{{ old('version', $settings->version) }}">
        <label class="block">Commission (%)<input class="fs-input mt-1 w-full" name="commission_percent" inputmode="decimal" required value="{{ old('commission_percent', \App\Support\Money::decimal($settings->commission_bps)) }}"><span class="text-sm text-text-muted">0 to 100%, up to two decimal places.</span></label>
        <label class="block">Delivery fee (PHP)<input class="fs-input mt-1 w-full" name="delivery_fee" inputmode="decimal" required value="{{ old('delivery_fee', \App\Support\Money::decimal($settings->delivery_fee_cents)) }}"><span class="text-sm text-text-muted">0 to PHP 1,000.</span></label>
        <label class="block">Payout hold (days)<input class="fs-input mt-1 w-full" name="payout_hold_days" type="number" min="0" max="90" required value="{{ old('payout_hold_days', $settings->payout_hold_days) }}"></label>
        <p class="text-sm text-text-muted">New fees apply to future checkout snapshots. Existing orders retain their original fees. The hold period controls current payout eligibility.</p>
        <button class="rounded-lg bg-accent px-4 py-2 text-white" type="submit">Save settings</button>
    </form>
@endsection
