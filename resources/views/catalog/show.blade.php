@extends('layouts.app')

@section('title', $product->name.' · FarSell')

@if(auth()->user()?->role !== \App\Enums\UserRole::Seller)
    @section('buyer-action-bar', 'true')
@endif

@section('content')
    @php
        $variantOptions = $product->variants->map(function ($variant) {
            $options = $variant->options ?: array_filter([$variant->option_name => $variant->option_value]);
            return ['id' => $variant->id, 'sku' => $variant->sku, 'stock' => (int) $variant->stock, 'price' => $variant->effectivePrice(), 'options' => $options];
        })->values();
        $optionNames = $variantOptions->flatMap(fn ($variant) => array_keys($variant['options']))->unique()->values();
    @endphp
    <div class="grid md:grid-cols-2 gap-6">
        <div class="aspect-square rounded-2xl bg-surface border border-surface-border flex items-center justify-center text-text-muted">
            @if($product->image_path)
                <x-product-image :src="$product->imageUrl()" :alt="$product->name" width="640" height="640"
                    :priority="true" class="h-full w-full rounded-2xl object-cover" />
            @else
                {{ $product->category?->name }}
            @endif
        </div>
        <div>
            <a href="{{ route('shops.show', $product->shop) }}" class="text-xs text-accent hover:text-accent font-medium">{{ $product->shop->name }}</a>
            <h1 class="text-xl font-semibold mt-1">{{ $product->name }}</h1>
            <p class="text-2xl font-semibold text-accent mt-2">{{ $product->formattedPrice() }}</p>
            @if(!$product->has_variants)
                <p class="mt-1 text-xs text-text-muted">SKU: {{ $product->sku }}</p>
            @endif
            @if ($product->compare_at_price)
                <p class="text-sm text-text-muted line-through">₱{{ number_format((float) $product->compare_at_price, 2) }}</p>
            @endif

            @if(isset($etaHours) && $etaHours !== null)
                <p class="mt-2 text-sm font-medium" style="color:rgb(var(--color-text-base));">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold" style="background-color:rgb(var(--color-accent-subtle)); color:rgb(var(--color-accent));">
                        <x-ui.icon name="delivery" size="sm" class="inline-block align-text-bottom" /> Est. delivery: {{ $etaHours <= 4 ? '2–4 hours' : 'Next day' }}
                    </span>
                </p>
            @endif

            @if (auth()->check() && auth()->user()->role === \App\Enums\UserRole::Buyer)
                <div class="mt-3"
                     x-data="savedItem(@js(route('account.saved.store', $product)), @js(auth()->user()->hasSavedProduct($product->id)))">
                    <x-ui.button variant="tertiary"
                        type="button"
                        @click="toggle()"
                        ::disabled="pending"
                        ::aria-pressed="saved"
                        ::aria-label="saved ? 'Remove from saved' : 'Save item'"
                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition-colors"
                        ::class="saved ? 'border-rose-300 text-rose-500 bg-rose-50' : 'border-surface-border text-text-muted hover:text-rose-400 hover:border-rose-300'"
                    >
                        {{-- Filled heart --}}
                        <x-ui.icon name="save" size="sm" variant="solid" x-show="saved" />
                        {{-- Outline heart --}}
                        <x-ui.icon name="save" size="sm" x-show="!saved" />
                        <span x-text="saved ? 'Saved' : 'Save'"></span>
                    </x-ui.button>
                    <p x-show="error" x-text="error" class="mt-2 text-sm text-error" role="status" style="display:none;"></p>
                </div>
            @endif
            <p class="text-sm text-text-muted mt-4">{{ $product->description }}</p>

            @if(auth()->user()?->role === \App\Enums\UserRole::Seller)
                <div class="mt-4 rounded-xl border border-surface-border bg-surface-muted p-3 text-sm text-text-muted">
                    Seller accounts can browse products, but purchasing is reserved for buyer accounts.
                    <x-ui.button variant="link" :href="route('seller.dashboard')" class="font-semibold text-accent hover:underline">Return to Seller Dashboard</x-ui.button>
                </div>
            @else
                <form method="post" action="{{ route('cart.store') }}" class="mt-4 flex flex-wrap items-end gap-3" data-product-options x-data="productOptions(@js($variantOptions), @js($optionNames), @js(['url' => route('products.availability', $product), 'stock' => $product->availableStock(), 'hasVariants' => $product->has_variants]))" @pageshow.window="restoreSelection()">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    @if($product->has_variants)
                        <input type="hidden" name="variant_id" x-bind:value="selectedVariant()?.id ?? ''">
                        <div class="grid w-full gap-3 sm:grid-cols-2">
                            @foreach($optionNames as $index => $optionName)
                                <label class="text-sm">{{ $optionName }}
                                    <select data-product-option="{{ $optionName }}" x-model="choices[optionNames[{{ $index }}]]" @change="clearUnavailable(optionNames[{{ $index }}])" class="mt-1 w-full rounded-lg border-surface-border text-sm focus:border-accent focus:ring-accent">
                                        <option value="">Choose {{ strtolower($optionName) }}</option>
                                        <template x-for="value in valuesFor(optionNames[{{ $index }}])" :key="value">
                                            <option :value="value" x-text="value" x-bind:disabled="!isAvailable(optionNames[{{ $index }}], value)"></option>
                                        </template>
                                    </select>
                                </label>
                            @endforeach
                        </div>
                        <p class="w-full text-xs text-text-muted" x-show="selectedVariant()" x-cloak>
                            Selected price: PHP <span x-text="Number(selectedVariant()?.price).toFixed(2)"></span> · <span x-text="selectedVariant()?.stock"></span> available · SKU: <span x-text="selectedVariant()?.sku"></span>
                        </p>
                    @endif
                    @php($quantityLimit = min(99, $product->availableStock()))
                    <div class="flex w-full flex-wrap items-center gap-3">
                        <div class="inline-flex items-center gap-2" role="group" aria-label="Quantity">
                            <x-ui.icon-button variant="secondary" label="Decrease quantity" icon="subtract" type="button" @click="qty = Math.max(1, Number(qty || 1) - 1)" x-bind:disabled="qty <= 1" class="disabled:opacity-40"></x-ui.icon-button>
                            <input type="number" name="qty" aria-label="Quantity" x-model.number="qty" min="1" max="{{ $quantityLimit }}" x-bind:max="quantityLimit() || 1" x-bind:disabled="!canPurchase()" @disabled($quantityLimit < 1) class="fs-input min-h-target w-16 p-0 text-center text-sm md:min-h-target-desktop">
                            <x-ui.icon-button variant="secondary" label="Increase quantity" icon="add" type="button" @click="qty = Math.min(quantityLimit(), Number(qty || 1) + 1)" x-bind:disabled="!canPurchase() || qty >= quantityLimit()" class="disabled:opacity-40"></x-ui.icon-button>
                        </div>
                        <span class="text-xs font-semibold" role="status" x-text="stockLabel()" :class="stockLabel() === 'IN STOCK' ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-300'">{{ $quantityLimit > 0 ? 'IN STOCK' : 'OUT OF STOCK' }}</span>
                    </div>
                    <div class="w-full">
                        <x-bottom-actions buyer>
                            <x-ui.button variant="primary" size="lg" type="submit" x-bind:disabled="!canPurchase()"  :disabled="$product->has_variants || $product->availableStock() < 1"  class="grow md:grow-0">Add to cart</x-ui.button>
                            <x-ui.button variant="secondary" type="submit" formaction="{{ route('cart.buy-now') }}" formmethod="post" x-bind:disabled="!canPurchase()"  :disabled="$product->has_variants || $product->availableStock() < 1"  class="shrink-0">Buy now</x-ui.button>
                        </x-bottom-actions>
                    </div>
                    <p class="w-full text-xs text-text-muted" role="status" x-text="stockMessage" x-show="stockMessage" x-cloak></p>
                </form>
            @endif

            @if(auth()->user()?->role === \App\Enums\UserRole::Buyer)
                <form method="post" action="{{ route('chat.start', $product) }}" class="mt-3">@csrf<x-ui.button variant="secondary" type="submit">Chat with seller</x-ui.button></form>
            @elseif(!auth()->check())
                <a href="{{ route('login') }}" class="mt-3 inline-block text-sm text-accent underline">Sign in to chat with seller</a>
            @endif
        </div>
    </div>

    @include('catalog.partials.reviews')

    @if ($related->isNotEmpty())
        <h2 class="text-base font-semibold mt-8 mb-3">More in {{ $product->category?->name }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach ($related as $item)
                @include('catalog.partials.card', ['product' => $item])
            @endforeach
        </div>
    @endif
@endsection
