@if($part->retry_at)<p class="mt-3 text-sm font-semibold">Next attempt: {{ $part->retry_at->format('M j, Y g:i A T') }}</p>@endif
@if($part->attempts->isNotEmpty())
    <div class="mt-4 rounded-xl border border-surface-border p-3 text-sm">
        <h3 class="font-semibold">Delivery attempts</h3>
        <p class="text-xs text-text-muted">Unsuccessful attempts do not confirm delivery, COD collection or earnings.</p>
        @foreach($part->attempts as $attempt)<p class="mt-2">#{{ $attempt->number }} · {{ $attempt->created_at->format('M j, Y g:i A') }} · {{ str_replace('_', ' ', $attempt->reason) }} · {{ $attempt->contact_method }}</p><p class="break-words">{{ $attempt->note }}</p>@endforeach
    </div>
@endif
@if($role === \App\Enums\UserRole::Rider && $part->status->value === 'in_transit' && !$part->retry_at?->isFuture())
    <details class="mt-4 rounded-xl border border-surface-border p-3"><summary class="cursor-pointer font-semibold">Report unsuccessful delivery attempt</summary>
        <form method="post" action="{{ route('fulfillments.update', $part) }}" class="mt-3 space-y-3">@csrf
            <input type="hidden" name="action" value="fail">
            <label class="block text-sm">Reason<select name="failure_reason" required class="mt-1 block w-full rounded-lg">@foreach(\App\Services\Orders\DeliveryExceptionService::REASONS as $reason)<option value="{{ $reason }}">{{ ucfirst(str_replace('_', ' ', $reason)) }}</option>@endforeach</select></label>
            <label class="block text-sm">Contact attempted<select name="contact_method" required class="mt-1 block w-full rounded-lg"><option value="phone">Phone call</option><option value="chat">Chat</option><option value="doorstep">At delivery address</option></select></label>
            <label class="block text-sm">What happened / contact attempts<textarea name="exception_note" minlength="10" maxlength="1000" required class="mt-1 block w-full rounded-lg"></textarea></label>
            <p class="text-xs">Do not use this if you collected payment. Contact admin instead. Do not include the buyer's PIN in this note.</p>
            <button class="btn-outline">Record failed attempt</button>
        </form>
    </details>
@elseif($isAdmin && $part->status->value === 'delivery_failed')
    @if($part->attempts->count() < config('marketplace.max_delivery_attempts'))
    <form method="post" action="{{ route('fulfillments.update', $part) }}" class="mt-4 space-y-3">@csrf
        <input type="hidden" name="action" value="retry">
        @if($part->usesExternalCourier())<label class="block text-sm"><input type="checkbox" name="courier_verified" value="1" required> I confirmed these retry arrangements with the courier</label>@endif
        <label class="block text-sm">Retry date/time ({{ config('app.timezone') }})<input type="datetime-local" name="retry_at" required class="mt-1 block w-full rounded-lg"></label>
        <label class="block text-sm">Buyer arrangement / instructions<textarea name="exception_note" required minlength="10" maxlength="1000" class="mt-1 block w-full rounded-lg"></textarea></label>
        <button class="btn-accent">{{ $part->usesExternalCourier() ? 'Record agreed courier retry' : 'Schedule retry with current rider' }}</button>
    </form>
    @endif
    <form method="post" action="{{ route('fulfillments.update', $part) }}" class="mt-4 space-y-3 border-t border-surface-border pt-3">@csrf
        <input type="hidden" name="action" value="return_to_seller">
        @if($part->usesExternalCourier())<label class="block text-sm"><input type="checkbox" name="courier_verified" value="1" required> I confirmed return arrangements with the courier</label>@endif
        <label class="block text-sm">Return instructions<textarea name="exception_note" required minlength="10" maxlength="1000" class="mt-1 block w-full rounded-lg"></textarea></label>
        <button class="btn-outline">Arrange return to seller</button>
    </form>
@elseif($isSeller && $part->status->value === 'returning')
    <form method="post" action="{{ route('fulfillments.update', $part) }}" class="mt-4 space-y-3">@csrf
        <input type="hidden" name="action" value="receive_return">
        <label class="block text-sm"><input type="checkbox" name="return_received" value="1" required> I physically received all items in this shipment</label>
        <label class="block text-sm">Inspection result<select name="return_disposition" required class="mt-1 block w-full rounded-lg"><option value="quarantine">Keep out of sellable stock / inspect or damaged</option><option value="restock">All items sellable — restore reserved quantities</option></select></label>
        <label class="block text-sm">Condition and handover note<textarea name="exception_note" required minlength="10" maxlength="1000" class="mt-1 block w-full rounded-lg"></textarea></label>
        <p class="text-xs">Stock is restored only when you choose sellable. No automatic rider earnings or failed-delivery charges are posted.</p>
        <button class="btn-accent">Confirm returned shipment</button>
    </form>
@elseif($part->status->value === 'returning')
    <p class="mt-4 text-sm font-semibold">Bring these items back to the pickup shop. The seller must confirm physical receipt. Do not collect COD.</p>
@elseif($part->status->value === 'delivery_failed')
    <p class="mt-4 text-sm">Await admin retry/return instructions. Do not mark this shipment delivered or collect COD.</p>
@endif
