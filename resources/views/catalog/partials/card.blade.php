@php
    $compact = $compact ?? false;
    $heroVariant = $heroVariant ?? false;
    $priorityImage = $priorityImage ?? $heroVariant;
@endphp
<article class="fs-card product-card {{ $compact ? 'w-36 shrink-0' : '' }}">
    <a href="{{ route('products.show', $product) }}" class="product-card-link">
        <div class="product-media {{ $heroVariant ? 'aspect-[2/1]' : 'aspect-square' }}"
             @if ($product->image_path) x-data="{ failed: false }" @endif>
            <div class="product-placeholder">
                <x-ui.icon name="product" size="lg" class="text-accent" />
                <span>{{ $product->category?->name ?? 'Marketplace find' }}</span>
                <span class="text-[11px]">Photo unavailable</span>
            </div>
            @if ($product->image_path)
                <x-product-image :src="$product->imageUrl()" :alt="$product->name"
                    width="640" :height="$heroVariant ? 320 : 640" :priority="$priorityImage"
                    x-show="!failed" x-on:error="failed = true" />
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
            <x-ui.icon-button variant="tertiary" label="Save item" ::title="saved ? 'Remove from saved' : 'Save item'" type="button" @click="toggle()" ::disabled="pending" ::aria-pressed="saved"
                    ::aria-label="saved ? 'Remove from saved' : 'Save item'" class="product-save">
                <x-ui.icon name="save" variant="solid" x-show="saved" x-cloak /><x-ui.icon name="save" x-show="!saved" />
            </x-ui.icon-button>
            <span x-show="error" x-text="error" role="status" class="absolute right-0 top-full mt-1 w-44 rounded-xl bg-surface p-2 text-xs text-error shadow-card-md" style="display:none;"></span>
        </div>
    @endif
</article>
