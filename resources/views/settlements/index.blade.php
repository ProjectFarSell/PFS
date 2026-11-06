@extends('layouts.portal')
@section('title', 'Commissions & settlements - FarSell')
@section('content')
<div class="mx-auto max-w-5xl space-y-4">
    <a href="{{ route($isAdmin ? 'admin.dashboard' : 'seller.dashboard') }}" class="text-sm text-accent underline">Back to dashboard</a>
    <h1 class="text-2xl font-bold">Commissions & settlements</h1>
    <p class="text-sm text-text-muted">Accounting records for new COD shipments completed by the buyer. These figures do not transfer money or confirm a seller payout. Fees are fixed at checkout; older orders keep their original rider earnings rules.</p>
    <p class="text-sm text-text-muted">COD handling and operating costs are recorded once per order on its first completed shipment. Rider pay is per completed shipment. Negative contribution is shown as a loss, never hidden.</p>
    @if($errors->any())<p role="alert" class="text-error">{{ $errors->first() }}</p>@endif
    <form method="get" class="flex flex-wrap gap-3"><label class="text-sm">From<input type="date" name="from" value="{{ request('from') }}" class="ml-2 rounded-lg"></label><label class="text-sm">To<input type="date" name="to" value="{{ request('to') }}" class="ml-2 rounded-lg"></label><button class="btn-outline">Filter</button></form>
    @forelse($rows as $row)
    @php
        $refund = $row->adjustments->sum('refund_cents');
        $reversal = $row->adjustments->sum('commission_reversal_cents');
        $sellerDebit = $row->adjustments->sum('seller_debit_cents');
    @endphp
    <article class="fs-card space-y-3 p-5">
        <h2 class="font-semibold">{{ $row->fulfillment->order->number }} · {{ $row->fulfillment->shop_name }} · Shipment #{{ $row->fulfillment_id }}</h2>
        <p class="text-xs text-text-muted">Recorded {{ $row->earned_at->format('M j, Y g:i A') }}</p>
        <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div><dt>Goods amount</dt><dd>PHP {{ number_format($row->goods_cents / 100, 2) }}</dd></div>
            <div><dt>Goods commission ({{ number_format($row->commission_bps / 100, 2) }}%)</dt><dd>PHP {{ number_format($row->commission_cents / 100, 2) }}</dd></div>
            <div><dt>Seller amount before reimbursements</dt><dd>PHP {{ number_format($row->seller_cents / 100, 2) }}</dd></div>
            <div><dt>Goods reimbursed</dt><dd>PHP {{ number_format($refund / 100, 2) }}</dd></div>
            <div><dt>Commission reversed</dt><dd>PHP {{ number_format($reversal / 100, 2) }}</dd></div>
            <div><dt>Seller net entitlement (not paid out)</dt><dd class="font-semibold">PHP {{ number_format(($row->seller_cents - $sellerDebit) / 100, 2) }}</dd></div>
            @if($isAdmin)
            <div><dt>Buyer delivery allocation</dt><dd>PHP {{ number_format($row->delivery_cents / 100, 2) }}</dd></div>
            <div><dt>Rider earnings</dt><dd>PHP {{ number_format($row->rider_cents / 100, 2) }}</dd></div>
            <div><dt>COD handling cost</dt><dd>PHP {{ number_format($row->cod_cost_cents / 100, 2) }}</dd></div>
            <div><dt>Operating cost allocation</dt><dd>PHP {{ number_format($row->operating_cost_cents / 100, 2) }}</dd></div>
            <div><dt>Platform contribution after reversals</dt><dd class="font-semibold">PHP {{ number_format(($row->contribution_cents - $reversal) / 100, 2) }}</dd></div>
            @endif
        </dl>
        @if($refund)<p class="text-xs text-text-muted">Goods commission is reversed proportionally when reimbursement is recorded. Completed delivery and operating costs remain incurred.</p>@endif
    </article>
    @empty<p class="fs-card p-5">No settlement records for this account or date range. Historical orders are not backfilled.</p>@endforelse
    {{ $rows->links() }}
</div>
@endsection
