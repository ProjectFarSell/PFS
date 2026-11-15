@extends('layouts.portal')
@section('title', 'Manage voucher')
@section('content')
@php($prefix = auth()->user()->role === \App\Enums\UserRole::Seller ? 'seller.vouchers' : 'admin.vouchers')
<div class="mx-auto max-w-2xl"><a class="text-accent underline" href="{{ route($prefix.'.index') }}">Back to vouchers</a><h1 class="my-4 text-xl font-semibold">{{ $voucher->exists ? 'Edit voucher' : 'Create voucher' }}</h1>
<p class="mb-4 text-sm text-text-muted">One voucher per checkout. Seller-funded discounts apply only to that shop and reduce its payout. FarSell pays for platform vouchers. Dates use {{ config('app.timezone') }}.</p>
@include('finance.errors')
<form method="post" action="{{ $voucher->exists ? route($prefix.'.update', $voucher) : route($prefix.'.store') }}" class="space-y-4 rounded-xl border border-surface-border bg-surface p-5" x-data="{ type: @js(old('type', $voucher->type)) }">
@csrf @if($voucher->exists) @method('PUT') <input type="hidden" name="version" value="{{ $voucher->version }}"> @endif
<label class="block text-sm">Code<input name="code" required maxlength="32" pattern="[A-Za-z0-9][A-Za-z0-9_-]{2,31}" value="{{ old('code', $voucher->code) }}" class="mt-1 block w-full rounded-lg border-surface-border uppercase"></label>
<label class="block text-sm">Name<input name="name" required maxlength="120" value="{{ old('name', $voucher->name) }}" class="mt-1 block w-full rounded-lg border-surface-border"></label>
<label class="block text-sm">Type<select name="type" x-model="type" class="mt-1 block w-full rounded-lg border-surface-border"><option value="fixed">Fixed merchandise discount (PHP)</option><option value="percentage">Percentage merchandise discount</option>@if($prefix === 'admin.vouchers' && !$voucher->shop_id)<option value="free_shipping">Free shipping (FarSell-funded)</option>@endif</select></label>
<label class="block text-sm" x-show="type !== 'free_shipping'">Discount amount / percent<input name="discount_value" type="number" min="0.01" step="0.01" :disabled="type === 'free_shipping'" :required="type !== 'free_shipping'" value="{{ old('discount_value', $voucher->exists ? \App\Support\Money::decimal($voucher->value) : '') }}" class="mt-1 block w-full rounded-lg border-surface-border"></label>
<label class="block text-sm">Minimum merchandise spend (PHP)<input name="minimum_spend" type="number" min="0" step="0.01" required value="{{ old('minimum_spend', \App\Support\Money::decimal($voucher->minimum_spend_cents ?? 0)) }}" class="mt-1 block w-full rounded-lg border-surface-border"></label>
<label class="block text-sm">Maximum saving (PHP, optional)<input name="maximum_discount" type="number" min="0.01" step="0.01" value="{{ old('maximum_discount', $voucher->maximum_discount_cents === null ? '' : \App\Support\Money::decimal($voucher->maximum_discount_cents)) }}" class="mt-1 block w-full rounded-lg border-surface-border"></label>
<label class="block text-sm">Total use limit (optional)<input name="usage_limit" type="number" min="1" max="1000000" value="{{ old('usage_limit', $voucher->usage_limit) }}" class="mt-1 block w-full rounded-lg border-surface-border"></label>
<label class="block text-sm">Uses per buyer<input name="per_buyer_limit" type="number" min="1" max="1000" required value="{{ old('per_buyer_limit', $voucher->per_buyer_limit) }}" class="mt-1 block w-full rounded-lg border-surface-border"></label>
<label class="block text-sm">Starts at (optional)<input name="starts_at" type="datetime-local" value="{{ old('starts_at', $voucher->starts_at?->format('Y-m-d\TH:i')) }}" class="mt-1 block w-full rounded-lg border-surface-border"></label>
<label class="block text-sm">Expires at (optional)<input name="expires_at" type="datetime-local" value="{{ old('expires_at', $voucher->expires_at?->format('Y-m-d\TH:i')) }}" class="mt-1 block w-full rounded-lg border-surface-border"></label>
<input type="hidden" name="is_active" value="0"><label class="block text-sm"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $voucher->is_active))> Enabled</label>
<x-ui.button type="submit">Save voucher</x-ui.button>
</form></div>
@endsection
