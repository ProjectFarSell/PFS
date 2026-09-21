@php
    $compact = $compact ?? false;
@endphp
<a href="{{ route('products.show', $product) }}" class="{{ $compact ? 'w-36 shrink-0' : '' }} block rounded-xl bg-surface border border-surface-border hover:border-accent transition-colors overflow-hidden">
    <div class="aspect-square bg-surface-muted flex items-center justify-center text-text-muted text-xs">
        @if($product->image_path)
            <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover">
        @else
            {{ $product->category?->name ?? 'Item' }}
        @endif
    </div>
    <div class="p-2">
        <p class="text-xs text-text-muted truncate">{{ $product->shop->name }}</p>
        <p class="text-sm font-medium line-clamp-2 min-h-[2.5rem]">{{ $product->name }}</p>
        <p class="text-accent font-semibold text-sm mt-1">{{ $product->formattedPrice() }}</p>
        @if ($product->compare_at_price)
            <p class="text-[11px] text-text-muted line-through">₱{{ number_format((float) $product->compare_at_price, 2) }}</p>
        @endif
    </div>
</a>
