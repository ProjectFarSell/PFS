@php
    $compact     = $compact     ?? false;
    $heroVariant = $heroVariant ?? false;
@endphp
<div class="relative {{ $compact ? 'w-36 shrink-0' : '' }}">
<a href="{{ route('products.show', $product) }}"
   class="fs-card overflow-hidden block
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

        {{-- Heart / save toggle (buyers only) --}}
        @if (auth()->check() && auth()->user()->role === \App\Enums\UserRole::Buyer)
            @php
                // Use a preloaded ID set when available (e.g. saved-items index) to avoid
                // a hasSavedProduct() query per card (N+1). Fall back to the helper
                // elsewhere (catalog index, homepage, etc.).
                $isSaved = isset($savedProductIds)
                    ? in_array($product->id, $savedProductIds ?? [])
                    : auth()->user()->hasSavedProduct($product->id);
            @endphp
            <button
                type="button"
                x-data="savedItem(@js($product->id), @js($isSaved))"
                @click.prevent="toggle()"
                :aria-label="saved ? 'Remove from saved' : 'Save item'"
                class="absolute top-2 right-2 p-1.5 rounded-full bg-white/80 backdrop-blur-sm transition-colors"
                :class="saved ? 'text-rose-500' : 'text-gray-400 hover:text-rose-400'"
            >
                {{-- Filled heart --}}
                <svg x-show="saved" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true">
                    <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" />
                </svg>
                {{-- Outline heart --}}
                <svg x-show="!saved" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                </svg>
            </button>
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
</div>
