@extends('layouts.app')

@section('title', $product->name.' · FarSell')

@section('content')
    @php
        $variantOptions = $product->variants->map(function ($variant) {
            $options = $variant->options ?: array_filter([$variant->option_name => $variant->option_value]);
            return ['id' => $variant->id, 'stock' => (int) $variant->stock, 'price' => $variant->effectivePrice(), 'options' => $options];
        })->values();
        $optionNames = $variantOptions->flatMap(fn ($variant) => array_keys($variant['options']))->unique()->values();
    @endphp
    <div class="grid md:grid-cols-2 gap-6">
        <div class="aspect-square rounded-2xl bg-surface border border-surface-border flex items-center justify-center text-text-muted">
            @if($product->image_path)
                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="h-full w-full rounded-2xl object-cover">
            @else
                {{ $product->category?->name }}
            @endif
        </div>
        <div>
            <a href="{{ route('shops.show', $product->shop) }}" class="text-xs text-accent hover:text-accent font-medium">{{ $product->shop->name }}</a>
            <h1 class="text-xl font-semibold mt-1">{{ $product->name }}</h1>
            <p class="text-2xl font-semibold text-accent mt-2">{{ $product->formattedPrice() }}</p>
            @if ($product->compare_at_price)
                <p class="text-sm text-text-muted line-through">₱{{ number_format((float) $product->compare_at_price, 2) }}</p>
            @endif

            @if(isset($etaHours) && $etaHours !== null)
                <p class="mt-2 text-sm font-medium" style="color:rgb(var(--color-text-base));">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold" style="background-color:rgb(var(--color-accent-subtle)); color:rgb(var(--color-accent));">
                        🚚 Est. delivery: {{ $etaHours <= 4 ? '2–4 hours' : 'Next day' }}
                    </span>
                </p>
            @endif

            @if (auth()->check() && auth()->user()->role === \App\Enums\UserRole::Buyer)
                <div class="mt-3"
                     x-data="savedItem(@js(route('account.saved.store', $product)), @js(auth()->user()->hasSavedProduct($product->id)))">
                    <button
                        type="button"
                        @click="toggle()"
                        :disabled="pending"
                        :aria-pressed="saved"
                        :aria-label="saved ? 'Remove from saved' : 'Save item'"
                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition-colors"
                        :class="saved ? 'border-rose-300 text-rose-500 bg-rose-50' : 'border-surface-border text-text-muted hover:text-rose-400 hover:border-rose-300'"
                    >
                        {{-- Filled heart --}}
                        <svg x-show="saved" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true">
                            <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" />
                        </svg>
                        {{-- Outline heart --}}
                        <svg x-show="!saved" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                        </svg>
                        <span x-text="saved ? 'Saved' : 'Save'"></span>
                    </button>
                    <p x-show="error" x-text="error" class="mt-2 text-sm text-error" role="status" style="display:none;"></p>
                </div>
            @endif
            <p class="text-sm text-text-muted mt-4">{{ $product->description }}</p>

            @if(auth()->user()?->role === \App\Enums\UserRole::Seller)
                <div class="mt-4 rounded-xl border border-surface-border bg-surface-muted p-3 text-sm text-text-muted">
                    Seller accounts can browse products, but purchasing is reserved for buyer accounts.
                    <a href="{{ route('seller.dashboard') }}" class="font-semibold text-accent hover:underline">Return to Seller Dashboard</a>
                </div>
            @else
                <form method="post" action="{{ route('cart.store') }}" class="mt-4 flex flex-wrap items-end gap-3" x-data="productOptions(@js($variantOptions), @js($optionNames))">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    @if($product->has_variants)
                        <input type="hidden" name="variant_id" x-bind:value="selectedVariant()?.id ?? ''">
                        <div class="grid w-full gap-3 sm:grid-cols-2">
                            @foreach($optionNames as $index => $optionName)
                                <label class="text-sm">{{ $optionName }}
                                    <select x-model="choices[optionNames[{{ $index }}]]" @change="clearUnavailable(optionNames[{{ $index }}])" class="mt-1 w-full rounded-lg border-surface-border text-sm focus:border-accent focus:ring-accent">
                                        <option value="">Choose {{ strtolower($optionName) }}</option>
                                        <template x-for="value in valuesFor(optionNames[{{ $index }}])" :key="value">
                                            <option :value="value" x-text="value" x-bind:disabled="!isAvailable(optionNames[{{ $index }}], value)"></option>
                                        </template>
                                    </select>
                                </label>
                            @endforeach
                        </div>
                        <p class="w-full text-xs text-text-muted" x-show="selectedVariant()" x-cloak>
                            Selected price: PHP <span x-text="Number(selectedVariant()?.price).toFixed(2)"></span> · <span x-text="selectedVariant()?.stock"></span> available
                        </p>
                    @endif
                    @php($quantityLimit = min(99, $product->availableStock()))
                    <div class="flex w-full flex-wrap items-center gap-3">
                        <div class="inline-flex h-10 overflow-hidden rounded-md border border-surface-border" role="group" aria-label="Quantity">
                            <button type="button" @click="qty = Math.max(1, Number(qty || 1) - 1)" x-bind:disabled="qty <= 1" class="w-10 border-r border-surface-border text-text-muted hover:bg-surface-muted disabled:opacity-40" aria-label="Decrease quantity">−</button>
                            <input type="number" name="qty" x-model.number="qty" min="1" max="{{ $quantityLimit }}" @if($product->has_variants) x-bind:max="selectedVariant()?.stock ?? 1" x-bind:disabled="!selectedVariant() || selectedVariant().stock < 1" @endif @disabled($quantityLimit < 1) class="h-full w-16 border-0 bg-transparent p-0 text-center text-sm focus:ring-0">
                            <button type="button" @if($product->has_variants) @click="qty = Math.min(selectedVariant()?.stock ?? 1, Number(qty || 1) + 1)" x-bind:disabled="!selectedVariant() || selectedVariant().stock < 1 || qty >= selectedVariant().stock" @else @click="qty = Math.min({{ $quantityLimit }}, Number(qty || 1) + 1)" x-bind:disabled="qty >= {{ $quantityLimit }}" @endif class="w-10 border-l border-surface-border text-text-muted hover:bg-surface-muted disabled:opacity-40" aria-label="Increase quantity">+</button>
                        </div>
                        @if($product->has_variants)
                            <span class="text-xs font-semibold" role="status" x-text="selectedVariant() ? (selectedVariant().stock > 0 ? 'IN STOCK' : 'OUT OF STOCK') : '{{ $quantityLimit > 0 ? 'IN STOCK' : 'OUT OF STOCK' }}'" x-bind:class="selectedVariant() ? (selectedVariant().stock > 0 ? 'text-green-600' : 'text-error') : '{{ $quantityLimit > 0 ? 'text-green-600' : 'text-error' }}'"></span>
                        @else
                            <span class="text-xs font-semibold {{ $quantityLimit > 0 ? 'text-green-600' : 'text-error' }}" role="status">{{ $quantityLimit > 0 ? 'IN STOCK' : 'OUT OF STOCK' }}</span>
                        @endif
                    </div>
                    <div class="w-full">
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" @if($product->has_variants) x-bind:disabled="!selectedVariant() || selectedVariant().stock < 1" @else @disabled($product->availableStock() < 1) @endif class="rounded-full bg-violet-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-violet-700 disabled:opacity-50">Add to cart</button>
                            <button type="submit" formaction="{{ route('cart.buy-now') }}" formmethod="post" @if($product->has_variants) x-bind:disabled="!selectedVariant() || selectedVariant().stock < 1" @else @disabled($product->availableStock() < 1) @endif class="rounded-full border border-accent px-5 py-2 text-sm font-medium text-accent transition-colors hover:bg-accent/5 disabled:opacity-50">Buy now</button>
                        </div>
                    </div>
                </form>
            @endif

            @if(auth()->user()?->role === \App\Enums\UserRole::Buyer)
                <form method="post" action="{{ route('chat.start', $product) }}" class="mt-3">@csrf<button class="btn-outline">Chat with seller</button></form>
            @elseif(!auth()->check())
                <a href="{{ route('login') }}" class="mt-3 inline-block text-sm text-accent underline">Sign in to chat with seller</a>
            @endif
        </div>
    </div>

    @if ($related->isNotEmpty())
        <h2 class="text-base font-semibold mt-8 mb-3">More in {{ $product->category?->name }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach ($related as $item)
                @include('catalog.partials.card', ['product' => $item])
            @endforeach
        </div>
    @endif
@endsection
