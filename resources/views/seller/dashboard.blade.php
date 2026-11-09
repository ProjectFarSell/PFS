@extends('layouts.portal')

@section('title', 'Seller Dashboard · FarSell')

@section('content')
    <section class="fs-card-raised p-5 sm:p-7">
        <p class="text-xs font-semibold uppercase tracking-widest text-accent">Seller Dashboard</p>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ $shop?->name ?? 'Your seller workspace' }}</h1>
            <div class="flex flex-wrap items-center gap-2">
                @if ($shop?->is_active)
                    <x-ui.button variant="primary" size="lg" :href="route('seller.products.create')" icon="add">Add product</x-ui.button>
                    <x-ui.button variant="secondary" :href="route('fulfillments.index')">Manage shop orders</x-ui.button>
                @elseif ($shop)
                    <x-ui.button variant="primary" size="lg" :href="route('fulfillments.index')">Manage shop orders</x-ui.button>
                @else
                    <x-ui.button variant="primary" size="lg" :href="route('seller.apply')">Apply to open a shop</x-ui.button>
                @endif
                <x-action-menu class="ml-auto" context="shop actions">
                    <p class="px-4 text-xs font-semibold text-text-muted">After-sales</p>
                    <a href="{{ route('returns.index') }}" class="dropdown-item">Returns & refunds</a>
                    <a href="{{ route('replacements.index') }}" class="dropdown-item">Replacement requests</a>
                    @if ($shop)
                        <p class="border-t border-surface-border px-4 pt-2 text-xs font-semibold text-text-muted">Finance</p>
                        <a href="{{ route('seller.sales') }}" class="dropdown-item">Sales summary</a>
                        <a href="{{ route('settlements.index') }}" class="dropdown-item">Commissions & settlements</a>
                    @endif
                    @if ($shop?->is_active)
                        <p class="border-t border-surface-border px-4 pt-2 text-xs font-semibold text-text-muted">Storefront</p>
                        <a href="{{ route('shops.show', $shop) }}" class="dropdown-item">View storefront</a>
                    @endif
                </x-action-menu>
            </div>
        </div>
        <p class="mt-2 text-sm text-text-muted">Manage listings, confirm incoming orders, and prepare shipments for pickup.</p>
        @if($shop)
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-ui.status-pill :status="$shop->is_active ? 'active' : 'inactive'">{{ $shop->is_active ? 'Shop active' : 'Shop inactive' }}</x-ui.status-pill>
                @unless($shop->is_active)
                    <p class="text-sm text-text-muted">Your shop is not visible in the public directory.</p>
                @endunless
            </div>
        @endif
    </section>

    @if(!$shop)
        <section class="fs-card mt-5 p-6">
            <h2 class="font-semibold">No shop linked to your account</h2>
            <p class="mt-2 text-sm text-text-muted">Submit your shop details for admin approval before listing products.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-ui.button variant="secondary" :href="route('account.profile')">Back to profile</x-ui.button>
                <x-ui.button variant="link" :href="route('fulfillments.index')">Manage shop orders</x-ui.button>
            </div>
        </section>
    @else
        <dl class="my-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach(['products' => 'Your products', 'active' => 'Active products', 'lowStock' => 'Low-stock active products', 'orderItems' => 'Linked order items'] as $key => $label)
                <div class="fs-card p-4">
                    <dt class="text-xs text-text-muted">{{ $label }}</dt>
                    <dd data-seller-inventory-stat="{{ $key }}" class="mt-2 text-2xl font-semibold">{{ number_format($stats[$key]) }}</dd>
                </div>
            @endforeach
        </dl>

        <section aria-labelledby="seller-products" class="fs-card overflow-hidden" data-live-inventory data-stock-url="{{ route('seller.inventory.stock') }}" data-stock-labels="true">
            <div class="border-b border-surface-border p-4">
                <h2 id="seller-products" class="scroll-mt-32 font-semibold">Your inventory</h2>
                <p class="mt-1 text-xs text-text-muted">Newest listings first. Low stock means 5 or fewer units. Variant quantities are the sum of active options.</p>
                <p data-stock-refresh-status class="mt-1 text-xs text-text-muted">Stock refreshes automatically while this page is visible.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Products owned by your shop</caption>
                    <thead class="bg-surface-muted text-xs text-text-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3">Product</th>
                            <th scope="col" class="px-4 py-3">Listing</th>
                            <th scope="col" class="px-4 py-3 text-right">Price</th>
                            <th scope="col" class="px-4 py-3 text-right">Stock</th>
                            @if($shop->is_active && auth()->user()->role === \App\Enums\UserRole::Seller)<th scope="col" class="px-4 py-3 text-right">Manage</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-border">
                        @forelse($products as $product)
                            <tr class="relative" data-inventory-product="{{ $product->id }}">
                                <th scope="row" class="px-4 py-3 font-medium break-words">
                                    @if($shop->is_active && $product->is_active)
                                        <x-ui.button variant="link" :href="route('products.show', $product)" class="relative z-10">{{ $product->name }}</x-ui.button>
                                    @else
                                        {{ $product->name }}
                                    @endif
                                </th>
                                <td class="px-4 py-3"><x-ui.status-pill data-inventory-listing :status="$product->is_active ? 'active' : 'inactive'" /></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">₱{{ number_format((float) $product->price, 2) }}</td>
                                <td data-inventory-stock class="px-4 py-3 text-right whitespace-nowrap {{ $product->availableStock() <= 5 ? 'text-error font-semibold' : '' }}">
                                    {{ $product->availableStock() }}{{ $product->availableStock() === 0 ? ' · Out of stock' : ($product->availableStock() <= 5 ? ' · Low stock' : '') }}
                                </td>
                                @if($shop->is_active && auth()->user()->role === \App\Enums\UserRole::Seller)<td class="px-4 py-3 text-right"><x-ui.button variant="link" :href="route('seller.products.edit', $product)" class="after:absolute after:inset-0 after:content-['']" aria-label="Edit {{ $product->name }}" icon="edit">Edit listing</x-ui.button></td>@endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $shop->is_active && auth()->user()->role === \App\Enums\UserRole::Seller ? 5 : 4 }}" class="px-4 py-8 text-center text-text-muted">No products in your shop yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($products->hasPages())
                <div class="border-t border-surface-border p-4">{{ $products->links() }}</div>
            @endif
        </section>

        <section aria-labelledby="seller-items" class="fs-card overflow-hidden mt-5">
            <div class="border-b border-surface-border p-4">
                <h2 id="seller-items" class="font-semibold">Your order items</h2>
                <p class="mt-1 text-xs text-text-muted">Only items linked to your current products are shown, including cancelled orders. Deleted products cannot be attributed here. Item totals exclude delivery fees and are not paid revenue.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Your shop's items from customer orders</caption>
                    <thead class="bg-surface-muted text-xs text-text-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3">Order / date</th>
                            <th scope="col" class="px-4 py-3">Item</th>
                            <th scope="col" class="px-4 py-3">Order status</th>
                            <th scope="col" class="px-4 py-3 text-right">Quantity</th>
                            <th scope="col" class="px-4 py-3 text-right">Item total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-border">
                        @forelse($items as $item)
                            <tr>
                                <th scope="row" class="px-4 py-3 font-medium">
                                    {{ $item->order?->number ?? 'Unavailable order' }}
                                    @if($item->order)
                                        <time class="block mt-1 text-xs font-normal text-text-muted whitespace-nowrap" datetime="{{ $item->order->created_at->toIso8601String() }}">{{ $item->order->created_at->format('M j, Y · g:i A') }}</time>
                                    @endif
                                </th>
                                <td class="px-4 py-3 break-words">{{ $item->name }}</td>
                                <td class="px-4 py-3"><x-ui.status-pill :status="$item->order?->status->value ?? 'unavailable'">{{ $item->order?->status->label() ?? 'Unavailable' }}</x-ui.status-pill></td>
                                <td class="px-4 py-3 text-right">{{ $item->qty }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">₱{{ number_format((float) $item->line_total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-text-muted">No order items linked to your products yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($items->hasPages())
                <div class="border-t border-surface-border p-4">{{ $items->links() }}</div>
            @endif
        </section>
    @endif
@endsection
