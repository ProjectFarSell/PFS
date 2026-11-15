@extends('layouts.portal')

@section('title', 'Active products | FarSell')

@section('content')
    <x-ui.button variant="link" :href="route('admin.dashboard')" class="text-sm text-accent underline">Back to Admin Dashboard</x-ui.button>
    <section class="fs-card mt-4 overflow-hidden" data-live-inventory data-stock-url="{{ route('admin.products.stock') }}">
        <div class="border-b border-surface-border p-5">
            <h1 class="text-xl font-semibold">Active products</h1>
            <nav aria-label="Product visibility" class="mt-3 flex gap-4 text-sm">
                <a class="underline" href="{{ route('admin.products.index') }}">Active</a>
                <a class="underline" href="{{ route('admin.products.index', ['visibility' => 'hidden']) }}">Hidden by admin</a>
                <a class="underline" href="{{ route('admin.products.index', ['visibility' => 'inactive']) }}">Unpublished</a>
            </nav>
            <p class="mt-2 text-sm text-text-muted">Current listings and inventory. Variant products show the sum of active variant stock.</p>
            <p data-stock-refresh-status class="mt-1 text-xs text-text-muted">Stock refreshes automatically while this page is visible.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="bg-surface-muted text-xs font-semibold text-text-base"><tr><th class="px-4 py-3">Product</th><th class="px-4 py-3">Shop / category</th><th class="px-4 py-3">Variants / SKU</th><th class="px-4 py-3 text-right">Available stock</th></tr></thead>
                <tbody class="divide-y divide-surface-border/40">
                    @forelse($products as $product)
<<<<<<< HEAD
                        <tr class="transition-colors hover:bg-surface-muted/50" data-inventory-product="{{ $product->id }}">
                            <th class="px-4 py-4 font-medium">{{ $product->name }}<span class="mt-1 block text-xs font-normal text-text-muted">Product ID {{ $product->id }}</span></th>
=======
                        <tr data-inventory-product="{{ $product->id }}">
                            <th class="px-4 py-4 font-medium">{{ $product->name }}<span class="mt-1 block text-xs font-normal text-text-muted">Product ID {{ $product->id }}</span>
                                @if($product->admin_hidden_at)<p class="mt-2 text-xs">Hidden: {{ $product->admin_hide_reason }}</p>@endif
                                <form class="mt-3 space-y-2" method="post" action="{{ route($product->admin_hidden_at ? 'admin.products.restore' : 'admin.products.hide', $product) }}">@csrf
                                    <label class="block text-xs">Reason<input class="mt-1 w-full rounded-lg" name="reason" required maxlength="1000"></label>
                                    <button class="text-xs underline" type="submit">{{ $product->admin_hidden_at ? 'Restore listing' : 'Hide listing' }}</button>
                                </form>
                            </th>
>>>>>>> 769933f81f43e7d3430c60a455faff4672240cb0
                            <td class="px-4 py-4">{{ $product->shop?->name ?? 'Unavailable shop' }}<span class="mt-1 block text-xs text-text-muted">{{ $product->category?->name ?? 'No category' }}</span></td>
                            <td class="px-4 py-4">
                                @if($product->has_variants)
                                    <ul class="space-y-1">
                                        @foreach($product->variants as $variant)
                                            <li><span class="font-medium">{{ collect($variant->options ?? [])->map(fn ($value, $name) => $name.': '.$value)->join(', ') ?: 'Option' }}</span><span class="block text-xs text-text-muted">{{ $variant->sku }} · <span data-inventory-variant="{{ $variant->id }}">{{ $variant->stock }} in stock</span></span></li>
                                        @endforeach
                                    </ul>
                                @else
                                    {{ $product->sku ?: 'No SKU' }}
                                @endif
                            </td>
                            <td data-inventory-stock class="px-4 py-4 text-right font-semibold tabular-nums">{{ number_format($product->availableStock()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-text-muted">No active products.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($products->hasPages())<div class="border-t border-surface-border p-4">{{ $products->links() }}</div>@endif
    </section>
@endsection
