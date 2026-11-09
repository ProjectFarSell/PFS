@extends('layouts.app')
@section('title', 'Request replacement - FarSell')
@section('content')
<div class="mx-auto max-w-2xl space-y-4">
    <x-ui.button variant="link" class="text-accent underline" :href="route('orders.show', $item->order)">Back to original order</x-ui.button>
    <h1 class="text-2xl font-bold">Request another variant</h1>
    <p>{{ $item->name }} · Original SKU: {{ $item->product_sku }}</p>
    <p class="text-sm text-text-muted">The seller received your original unpaid COD shipment. Request a replacement order for review. If approved, you must review current prices and delivery charges and place a new COD order. This is not a free exchange or an automatic charge.</p>
    @if($errors->any())<p role="alert" class="text-error">{{ $errors->first() }}</p>@endif
    <form method="post" action="{{ route('replacements.store', $item) }}" class="fs-card space-y-4 p-5">@csrf
        @if($item->product?->has_variants)
        <label class="block text-sm">Desired variant<select name="variant_id" required class="mt-1 block w-full rounded-lg">
            <option value="">Choose size / color</option>
            @foreach($item->product->variants->where('is_active', true) as $variant)
            <option value="{{ $variant->id }}">{{ collect($variant->options)->map(fn($v, $k) => $k.': '.$v)->join(', ') }} · {{ $variant->sku }} · PHP {{ number_format($variant->effectivePrice(), 2) }} · {{ $variant->stock }} available</option>
            @endforeach
        </select></label>
        @endif
        <label class="block text-sm">Quantity<input type="number" name="quantity" min="1" max="{{ min(99, $item->qty) }}" value="1" required class="mt-1 block w-full rounded-lg"></label>
        <label class="block text-sm">Reason and requested change<textarea name="note" required minlength="10" maxlength="1000" class="mt-1 block w-full rounded-lg">{{ old('note') }}</textarea></label>
        <x-ui.button variant="primary" size="lg" type="submit">Submit replacement request</x-ui.button>
    </form>
</div>
@endsection
