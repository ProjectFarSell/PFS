@extends($role === \App\Enums\UserRole::Buyer ? 'layouts.app' : 'layouts.portal')
@section('title', 'Replacement requests - FarSell')
@section('content')
<div class="mx-auto max-w-3xl space-y-4">
    <h1 class="text-2xl font-bold">Replacement requests</h1>
    <p class="text-sm text-text-muted">Replacement orders follow confirmed return of unpaid COD goods. Approval does not reserve stock or waive current item/delivery prices. Paid purchases continue through Returns & refunds.</p>
    @if($errors->any())<p role="alert" class="text-error">{{ $errors->first() }}</p>@endif
    @forelse($cases as $case)
    <article class="fs-card space-y-3 p-5">
        <h2 class="font-semibold">{{ $case->item->name }} · {{ $case->item->order->number }}</h2>
        <p class="text-sm">Original SKU: {{ $case->item->product_sku }} · Requested SKU: {{ $case->variant?->sku ?? $case->item->product_sku }} · Quantity: {{ $case->quantity }}</p>
        <p class="font-semibold text-accent">{{ ucfirst($case->status) }}</p>
        <p class="break-words text-sm">{{ $case->note }}</p>
        @if($case->review_note)<p class="break-words text-sm">Review: {{ $case->review_note }}</p>@endif
        @if($case->newOrder)
            <p class="text-sm">Linked order: {{ $case->newOrder->number }} · {{ $case->newOrder->status->label() }}</p>
            @if($role !== \App\Enums\UserRole::Seller)<a href="{{ route('orders.show', $case->newOrder) }}" class="text-sm text-accent underline">View replacement order</a>@endif
        @elseif($role === \App\Enums\UserRole::Buyer && $case->status === 'approved')
            <form method="post" action="{{ route('replacements.checkout', $case) }}">@csrf<button class="btn-accent">Review current price & checkout</button></form>
        @elseif($role !== \App\Enums\UserRole::Buyer && $case->status === 'pending')
            <form method="post" action="{{ route('replacements.review', $case) }}" class="space-y-3">@csrf
                <label class="block text-sm">Decision<select name="decision" class="mt-1 block w-full rounded-lg"><option value="approved">Approve new COD order</option><option value="rejected">Decline</option></select></label>
                <label class="block text-sm">Review and instructions<textarea name="review_note" required minlength="10" maxlength="1000" class="mt-1 block w-full rounded-lg"></textarea></label>
                <button class="btn-outline">Save decision</button>
            </form>
        @endif
    </article>
    @empty<p class="fs-card p-5">No replacement requests. Buyers can request one from the original order after the seller confirms the unpaid shipment was returned.</p>@endforelse
    {{ $cases->links() }}
</div>
@endsection
