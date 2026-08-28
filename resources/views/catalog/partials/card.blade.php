@php
    $compact = $compact ?? false;
@endphp
<a href="{{ route('products.show', $product) }}" class="{{ $compact ? 'w-36 shrink-0' : '' }} block rounded-xl bg-white border border-stone-200 overflow-hidden">
    <div class="aspect-square bg-stone-100 flex items-center justify-center text-stone-400 text-xs">
        {{ $product->category?->name ?? 'Item' }}
    </div>
    <div class="p-2">
        <p class="text-xs text-stone-500 truncate">{{ $product->shop->name }}</p>
        <p class="text-sm font-medium line-clamp-2 min-h-[2.5rem]">{{ $product->name }}</p>
        <p class="text-orange-600 font-semibold text-sm mt-1">{{ $product->formattedPrice() }}</p>
        @if ($product->compare_at_price)
            <p class="text-[11px] text-stone-400 line-through">₱{{ number_format((float) $product->compare_at_price, 2) }}</p>
        @endif
    </div>
</a>
