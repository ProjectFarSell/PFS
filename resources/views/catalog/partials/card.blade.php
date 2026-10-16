@php
    $compact = $compact ?? false;
    $heroVariant = $heroVariant ?? false;
@endphp
<article class="fs-card product-card {{ $compact ? 'w-36 shrink-0' : '' }}">
    <a href="{{ route('products.show', $product) }}" class="product-card-link">
        <div class="product-media {{ $heroVariant ? 'aspect-[2/1]' : 'aspect-square' }}"
             @if ($product->image_path) x-data="{ failed: false }" @endif>
            <div class="product-placeholder">
                <svg class="h-12 w-12 text-accent/40" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="m12 23 20-11 20 11v23L32 57 12 46Zm0 0 20 12 20-12M32 35v22M22 18l20 12" stroke-linejoin="round"/>
                    <path d="m21 8 5 3m17-3-5 3M7 12l5 5m45-5-5 5" stroke-linecap="round"/>
                </svg>
                <span>{{ $product->category?->name ?? 'Marketplace find' }}</span>
                <span class="text-[11px]">Photo unavailable</span>
            </div>
            @if ($product->image_path)
                <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}"
                     loading="{{ $heroVariant ? 'eager' : 'lazy' }}" decoding="async"
                     width="640" height="{{ $heroVariant ? '320' : '640' }}"
                     x-show="!failed" x-on:error="failed = true">
            @endif
        </div>
        <div class="flex flex-1 flex-col p-3 sm:p-4">
            <p class="text-xs text-text-muted truncate">{{ $product->shop->name }}</p>
            <h3 class="mt-1 text-sm font-medium leading-snug line-clamp-2 min-h-[2.5rem]">{{ $product->name }}</h3>
            <div class="mt-auto pt-3 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <p class="text-base font-bold tracking-tight tabular-nums text-accent">{{ $product->formattedPrice() }}</p>
                @if ($product->compare_at_price && $product->compare_at_price > $product->price)
                    <s class="text-xs text-text-muted tabular-nums">₱{{ number_format((float) $product->compare_at_price, 2) }}</s>
                @endif
            </div>
            @if ($product->shop->city)
                <p class="mt-2 text-[11px] text-text-muted truncate">{{ $product->shop->city }}</p>
            @endif
        </div>
    </a>
    @if (auth()->check() && auth()->user()->role === \App\Enums\UserRole::Buyer)
        @php
            $isSaved = isset($savedProductIds) ? in_array($product->id, $savedProductIds) : auth()->user()->hasSavedProduct($product->id);
        @endphp
        <div class="absolute top-2 right-2 z-10" x-data="savedItem(@js(route('account.saved.store', $product)), @js($isSaved))">
            <button type="button" @click="toggle()" :disabled="pending" :aria-pressed="saved"
                    :aria-label="saved ? 'Remove from saved' : 'Save item'" class="product-save">
                <svg class="h-5 w-5" viewBox="0 0 24 24" :fill="saved ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>
                </svg>
            </button>
            <span x-show="error" x-text="error" role="status" class="absolute right-0 top-full mt-1 w-44 rounded-xl bg-surface p-2 text-xs text-error shadow-card-md" style="display:none;"></span>
        </div>
    @endif
</article>
