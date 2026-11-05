@if(auth()->user()->role === \App\Enums\UserRole::Buyer)
<section class="mt-6 space-y-3 print:hidden">
    <a href="{{ route('returns.index') }}" class="text-accent underline text-sm">My returns & refunds</a>
    @foreach($order->items as $item)
        @if($item->returnRequest)
            <p class="text-sm">{{ $item->name }}: {{ $item->returnRequest->label() }}</p>
        @elseif(in_array($order->fulfillments->firstWhere('id', $item->fulfillment_id)?->status->value, ['delivered', 'completed'], true))
            <details class="rounded-xl border border-surface-border bg-surface p-4">
                <summary class="cursor-pointer text-sm font-semibold">Request return / refund: {{ $item->name }}</summary>
                <form method="post" action="{{ route('returns.store', $item) }}" enctype="multipart/form-data" class="mt-3 space-y-3">@csrf
                    <p class="text-xs text-text-muted">One request per purchased item. Merchandise only; delivery fees excluded.</p>
                    <label class="block text-sm">Quantity<input type="number" name="quantity" min="1" max="{{ $item->qty }}" value="1" required class="ml-2 rounded-lg border border-surface-border bg-surface p-2"></label>
                    <label class="block text-sm">Reason<select name="reason" required class="ml-2 rounded-lg border border-surface-border bg-surface p-2"><option value="damaged">Damaged</option><option value="incorrect">Incorrect item</option><option value="missing">Missing item</option><option value="other">Other</option></select></label>
                    <label class="block text-sm">Describe the problem<textarea name="details" required minlength="10" maxlength="2000" class="mt-1 block w-full rounded-lg border border-surface-border bg-surface p-2"></textarea></label>
                    <label class="block text-sm">Evidence photo (optional JPG/PNG/WebP, up to 4 MB)<input type="file" name="evidence" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm"></label>
                    <button class="btn-outline">Submit request</button>
                </form>
            </details>
        @endif
    @endforeach
</section>
@endif
