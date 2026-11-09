@extends('layouts.portal')
@section('title', 'Sales summary - FarSell')
@section('content')
<div class="space-y-6">
    <div><x-ui.button variant="link" :href="route('seller.dashboard')" class="text-sm text-accent underline">Back to dashboard</x-ui.button><h1 class="mt-2 text-2xl font-bold">{{ $shop->name }} — Sales summary</h1></div>
    @if($errors->any())<p role="alert" class="text-error">{{ $errors->first() }}</p>@endif
    <form method="get" class="flex flex-wrap items-end gap-3">
        <label class="text-sm">From<input type="date" name="from" value="{{ $from }}" required class="mt-1 block rounded-lg border border-surface-border bg-surface p-2"></label>
        <label class="text-sm">To<input type="date" name="to" value="{{ $to }}" required class="mt-1 block rounded-lg border border-surface-border bg-surface p-2"></label>
        <x-ui.button variant="primary" size="lg" type="submit">Apply dates</x-ui.button><x-ui.button variant="secondary" type="submit" name="export" value="csv" icon="export">Download CSV</x-ui.button>
    </form>
    <p class="text-sm text-text-muted">Sales use buyer-confirmed shipment completion dates and merchandise amounts only. Delivery fees are rider earnings. Reimbursements use the date recorded, including refunds for older sales. Net here is sales less reimbursements, not profit. Demo payments are excluded. Existing completed shipments without a completion event use their last update date.</p>
    <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach(['sales' => 'Completed COD sales', 'refunds' => 'Recorded reimbursements', 'net' => 'Sales less reimbursements', 'demo' => 'Demo sales (excluded)'] as $key => $label)
            <div class="rounded-xl border border-surface-border bg-surface p-4"><dt class="text-sm text-text-muted">{{ $label }}</dt><dd class="mt-2 text-xl font-bold">PHP {{ number_format((float) $stats[$key], 2) }}</dd></div>
        @endforeach
        @foreach(['units' => 'Units sold (COD)', 'shipments' => 'Completed COD shipments', 'pending' => 'Pending shipments placed in period', 'cancelled' => 'Cancelled / rejected shipments placed in period'] as $key => $label)
            <div class="rounded-xl border border-surface-border bg-surface p-4"><dt class="text-sm text-text-muted">{{ $label }}</dt><dd class="mt-2 text-xl font-bold">{{ number_format($stats[$key]) }}</dd></div>
        @endforeach
    </dl>
    <section class="rounded-xl border border-surface-border bg-surface p-5"><h2 class="mb-4 font-bold">Best-selling products / variants (COD)</h2>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Product</th><th class="p-2">SKU</th><th class="p-2">Units</th><th class="p-2">Merchandise sales</th></tr></thead><tbody>
            @forelse($bestSellers as $item)<tr class="border-t border-surface-border"><td class="p-2">{{ $item->name }}</td><td class="p-2">{{ $item->product_sku }}</td><td class="p-2">{{ $item->units }}</td><td class="p-2">PHP {{ number_format((float) $item->merchandise_sales, 2) }}</td></tr>
            @empty<tr><td colspan="4" class="p-4 text-text-muted">No completed COD sales in this period.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
