@extends('layouts.portal')

@section('title', 'Seller Dashboard · FarSell')

@section('content')
    <section class="fs-card-raised p-5 sm:p-7">
        <p class="text-xs font-semibold uppercase tracking-widest text-accent">Seller Dashboard</p>
        <h1 class="mt-2 text-2xl font-semibold">{{ $shop?->name ?? 'Your seller workspace' }}</h1>
        <p class="mt-2 text-sm text-text-muted">Manage listings, confirm incoming orders, and prepare shipments for pickup.</p>
        <a href="{{ route('fulfillments.index') }}" class="btn-accent mt-4">Manage shop orders</a>
        @if($shop)
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <span class="badge {{ $shop->is_active ? 'badge-success' : 'badge-neutral' }}">{{ $shop->is_active ? 'Shop active' : 'Shop inactive' }}</span>
                @if($shop->is_active)
                    <a href="{{ route('shops.show', $shop) }}" class="text-sm text-accent underline">View storefront</a>
                    @if(auth()->user()->role === \App\Enums\UserRole::Seller)<a href="{{ route('seller.products.create') }}" class="btn-accent">Add product</a>@endif
                @else
                    <p class="text-sm text-text-muted">Your shop is not visible in the public directory.</p>
                @endif
            </div>
        @endif
    </section>

    @if(!$shop)
        <section class="fs-card mt-5 p-6">
            <h2 class="font-semibold">No shop linked to your account</h2>
            <p class="mt-2 text-sm text-text-muted">Submit your shop details for admin approval before listing products.</p>
            @if(auth()->user()->role === \App\Enums\UserRole::Seller)<a href="{{ route('seller.apply') }}" class="btn-accent mt-4">Apply to open a shop</a>@endif
            <a href="{{ route('account.profile') }}" class="btn-outline mt-4">Back to profile</a>
        </section>
    @else
        <dl class="my-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach(['products' => 'Your products', 'active' => 'Active products', 'lowStock' => 'Low-stock active products', 'orderItems' => 'Linked order items'] as $key => $label)
                <div class="fs-card p-4">
                    <dt class="text-xs text-text-muted">{{ $label }}</dt>
                    <dd class="mt-2 text-2xl font-semibold">{{ number_format($stats[$key]) }}</dd>
                </div>
            @endforeach
        </dl>

        <section aria-labelledby="seller-products" class="fs-card overflow-hidden">
            <div class="border-b border-surface-border p-4">
                <h2 id="seller-products" class="font-semibold">Your inventory</h2>
                <p class="mt-1 text-xs text-text-muted">Newest listings first. Low stock means 5 or fewer units. Quantities are product-level, not per variant.</p>
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
                            @if($shop->is_active && auth()->user()->role === \App\Enums\UserRole::Seller)<th scope="col" class="px-4 py-3">Manage</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-border">
                        @forelse($products as $product)
                            <tr>
                                <th scope="row" class="px-4 py-3 font-medium break-words">
                                    @if($shop->is_active && $product->is_active)
                                        <a href="{{ route('products.show', $product) }}" class="text-accent underline">{{ $product->name }}</a>
                                    @else
                                        {{ $product->name }}
                                    @endif
                                </th>
                                <td class="px-4 py-3"><span class="badge {{ $product->is_active ? 'badge-success' : 'badge-neutral' }}">{{ $product->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">₱{{ number_format((float) $product->price, 2) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap {{ $product->stock <= 5 ? 'text-error font-semibold' : '' }}">
                                    {{ $product->stock }}{{ $product->stock === 0 ? ' · Out of stock' : ($product->stock <= 5 ? ' · Low stock' : '') }}
                                </td>
                                @if($shop->is_active && auth()->user()->role === \App\Enums\UserRole::Seller)<td class="px-4 py-3"><a href="{{ route('seller.products.edit', $product) }}" class="text-accent underline" aria-label="Edit {{ $product->name }}">Edit listing</a></td>@endif
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
                                <td class="px-4 py-3"><span class="badge badge-neutral">{{ $item->order?->status->label() ?? 'Unavailable' }}</span></td>
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
