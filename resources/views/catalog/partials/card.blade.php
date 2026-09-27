@php
    $compact     = $compact     ?? false;
    $heroVariant = $heroVariant ?? false;
@endphp
<a href="{{ route('products.show', $product) }}"
   class="fs-card overflow-hidden block
          {{ $compact     ? 'w-36 shrink-0' : '' }}
          {{ $heroVariant ? 'col-span-2 row-span-2' : '' }}">

    {{-- Image / placeholder area --}}
    <div class="relative {{ $heroVariant ? 'aspect-[2/1]' : 'aspect-square' }}
                flex items-center justify-center text-xs"
         style="background-color: rgb(var(--color-surface-muted)); color: rgb(var(--color-text-muted));">

        @if ($product->image_path)
            <img src="{{ asset('storage/' . $product->image_path) }}"
                 alt="{{ $product->name }}"
                 loading="{{ $heroVariant ? 'eager' : 'lazy' }}"
                 class="h-full w-full object-cover">
        @else
            {{ $product->category?->name ?? 'Item' }}
        @endif

        {{-- Hero gradient overlay + Featured badge --}}
        @if ($heroVariant)
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent
                        pointer-events-none"></div>
            <span class="absolute top-2 left-2 badge badge-accent text-[10px] font-semibold">
                Featured
            </span>
        @endif
    </div>

    {{-- Info --}}
    <div class="{{ $heroVariant ? 'p-3' : 'p-2' }}">
        <p class="text-xs truncate" style="color: rgb(var(--color-text-muted));">
            {{ $product->shop->name }}
        </p>
        <p class="text-sm font-medium line-clamp-2 min-h-[2.5rem]"
           style="color: rgb(var(--color-text-base));">
            {{ $product->name }}
        </p>
        <p class="font-semibold text-sm mt-1"
           style="color: rgb(var(--color-accent));">
            {{ $product->formattedPrice() }}
        </p>
        @if ($product->compare_at_price)
            <p class="text-[11px] line-through"
               style="color: rgb(var(--color-text-muted));">
                ₱{{ number_format((float) $product->compare_at_price, 2) }}
            </p>
        @endif
    </div>
</a>
