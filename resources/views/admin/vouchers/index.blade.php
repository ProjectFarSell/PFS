@extends('layouts.portal')
@section('title', 'Vouchers')
@section('content')
@php($prefix = auth()->user()->role === \App\Enums\UserRole::Seller ? 'seller.vouchers' : 'admin.vouchers')
<div class="mb-5 flex items-center justify-between gap-3"><h1 class="text-xl font-semibold">Vouchers</h1><x-ui.button :href="route($prefix.'.create')">Create voucher</x-ui.button></div>
<p class="mb-4 text-sm text-text-muted">Buyers can use one voucher per checkout. Shop vouchers reduce that seller’s payout; FarSell vouchers are paid for by the platform. Uses remain counted after cancellation or a return.</p>
@include('finance.errors')
<div class="overflow-x-auto rounded-xl border border-surface-border"><table class="w-full text-left text-sm"><thead><tr class="bg-surface"><th class="p-3">Code / name</th><th class="p-3">Funding</th><th class="p-3">Discount</th><th class="p-3">Uses</th><th class="p-3">Status / expiry</th><th class="p-3">Manage</th></tr></thead><tbody>
@forelse($vouchers as $voucher)
@php($availability = !$voucher->is_active ? 'Disabled' : ($voucher->expires_at?->lte(now()) ? 'Expired' : ($voucher->starts_at?->isFuture() ? 'Scheduled' : ($voucher->usage_limit !== null && $voucher->uses >= $voucher->usage_limit ? 'Use limit reached' : 'Available'))))
<tr class="border-t border-surface-border">
    <td class="p-3"><strong>{{ $voucher->code }}</strong><br>{{ $voucher->name }}</td>
    <td class="p-3">{{ $voucher->shop_id ? 'Seller · '.$voucher->shop?->name : 'FarSell' }}</td>
    <td class="p-3">{{ $voucher->type === 'free_shipping' ? 'Free shipping' : ($voucher->type === 'percentage' ? \App\Support\Money::decimal($voucher->value).'%' : 'PHP '.\App\Support\Money::decimal($voucher->value)) }}</td>
    <td class="p-3">{{ $voucher->uses }} / {{ $voucher->usage_limit ?? 'Unlimited' }}<br>{{ $voucher->per_buyer_limit }} per buyer</td>
    <td class="p-3">{{ $availability }}<br>{{ $voucher->expires_at?->format('M j, Y H:i') ?? 'No expiry' }}</td>
    <td class="p-3">
        <a class="text-accent underline" href="{{ route($prefix.'.edit', $voucher) }}">Edit / disable</a>
        @if((int) $voucher->uses === 0)
            <form method="post" action="{{ route($prefix.'.destroy', $voucher) }}" class="mt-2">
                @csrf @method('DELETE')
                <input type="hidden" name="version" value="{{ $voucher->version }}">
                <x-destructive-confirmation name="Delete voucher" confirm-label="Delete voucher" description="Delete the unused voucher {{ $voucher->code }}? Buyers will no longer be able to apply this code." class="justify-start" />
            </form>
        @else
            <p class="mt-2 text-xs text-text-muted">Used vouchers retain order history. Disable to stop new uses.</p>
        @endif
    </td>
</tr>
@empty<tr><td colspan="6" class="p-5">No vouchers yet.</td></tr>@endforelse
</tbody></table></div><div class="mt-4">{{ $vouchers->links() }}</div>
@endsection
