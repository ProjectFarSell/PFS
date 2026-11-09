@extends(auth()->user()->role === \App\Enums\UserRole::Buyer ? 'layouts.app' : 'layouts.portal')
@section('title', 'Returns & refunds - FarSell')
@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <h1 class="text-2xl font-bold">Returns & refunds</h1>
    <p class="text-sm text-text-muted">Requests cover merchandise, excluding delivery fees. Sellers recommend a decision; admins approve or reject. Reimbursements happen outside FarSell and are recorded here. Returned goods are not automatically restocked.</p>
    @if($errors->any())<div role="alert" class="rounded-xl border border-error p-4">{{ $errors->first() }}</div>@endif
    <form method="get" class="flex flex-wrap gap-3"><label>Status <select name="status" class="rounded-lg border border-surface-border bg-surface p-2"><option value="">All requests</option>@foreach(\App\Models\ReturnRequest::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></label><x-ui.button variant="secondary" type="submit">Filter</x-ui.button></form>
    @forelse($cases as $case)
        <article class="rounded-xl border border-surface-border bg-surface p-5 space-y-3">
            <h2 class="font-bold">Request R{{ $case->id }} · {{ $case->item->name }}</h2>
            <p class="text-sm">Order {{ $case->item->order->number }} · SKU {{ $case->item->product_sku }} · Quantity {{ $case->quantity }}</p>
            <p class="font-semibold text-accent">{{ $case->label() }}</p>
            <p class="text-sm">{{ ucfirst($case->reason) }} — {{ $case->details }}</p>
            <p class="text-sm">Requested: PHP {{ number_format((float) $case->requested_amount, 2) }}@if($case->approved_amount !== null) · Approved: PHP {{ number_format((float) $case->approved_amount, 2) }}@endif</p>
            @if($case->return_required)<p class="text-sm">Return of goods required. Follow the handover instructions in the review below.</p>@endif
            @if($case->refunded_at)<p class="text-sm">Reimbursement recorded {{ $case->refunded_at->format('M j, Y g:i A') }} · Reference: {{ $case->refund_reference }}</p>@endif
            @if($case->evidence_path)<x-ui.button variant="link" class="text-sm text-accent underline" :href="route('returns.evidence', $case)" target="_blank" rel="noopener">View private evidence photo</x-ui.button>@endif
            <ol class="space-y-2 border-l border-surface-border pl-4 text-sm">
                @foreach($case->events as $event)<li><time class="text-xs text-text-muted">{{ $event->created_at->format('M j, Y g:i A') }}</time><p>{{ ucfirst(str_replace('_', ' ', $event->status)) }}: {{ $event->note }}</p></li>@endforeach
            </ol>
            @php($role = auth()->user()->role->value)
            @if(($role === 'seller' && $case->status === 'pending') || ($role === 'admin' && in_array($case->status, ['pending', 'seller_approved', 'seller_declined', 'approved'])))
                <form method="post" action="{{ route('returns.review', $case) }}" class="space-y-3 border-t border-surface-border pt-3">@csrf
                    @if($role === 'seller')
                        <label class="block text-sm">Recommendation<select name="action" class="ml-2 rounded-lg border border-surface-border bg-surface p-2"><option value="seller_approved">Recommend approval</option><option value="seller_declined">Dispute request</option></select></label>
                    @elseif($case->status === 'approved')
                        <input type="hidden" name="action" value="refunded">
                        @if($case->return_required)<label class="block text-sm"><input type="checkbox" name="return_received" value="1" required> Returned goods have been received</label>@endif
                        <label class="block text-sm">Reimbursement reference<input name="refund_reference" required minlength="5" maxlength="255" class="mt-1 w-full rounded-lg border border-surface-border bg-surface p-2"></label>
                        <label class="block text-sm"><input type="checkbox" name="reimbursement_paid" value="1" required> PHP {{ number_format((float) $case->approved_amount, 2) }} has already been reimbursed outside FarSell</label>
                    @else
                        <label class="block text-sm">Decision<select name="action" class="ml-2 rounded-lg border border-surface-border bg-surface p-2"><option value="approved">Approve</option><option value="rejected">Reject</option></select></label>
                        <label class="block text-sm">Approved amount (when approving)<input type="number" name="approved_amount" min="0.01" max="{{ $case->requested_amount }}" step="0.01" class="ml-2 rounded-lg border border-surface-border bg-surface p-2"></label>
                        <label class="block text-sm"><input type="checkbox" name="return_required" value="1"> Require returned goods before reimbursement</label>
                    @endif
                    <label class="block text-sm">Response / handover instructions<textarea name="note" required minlength="5" maxlength="2000" class="mt-1 block w-full rounded-lg border border-surface-border bg-surface p-2"></textarea></label>
                    <x-ui.button variant="primary" size="lg" type="submit">{{ $case->status === 'approved' ? 'Record reimbursement' : 'Submit review' }}</x-ui.button>
                </form>
            @endif
        </article>
    @empty
        <p class="rounded-xl border border-surface-border p-6">No return/refund requests found. Buyers can submit a request from a delivered order.</p>
    @endforelse
    {{ $cases->links() }}
</div>
@endsection
