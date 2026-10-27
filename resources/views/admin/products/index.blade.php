@extends('layouts.portal')

@section('title', 'Active products · FarSell')

@section('content')
    <a href="{{ route('admin.dashboard') }}" class="text-sm text-accent underline">Back to Admin Dashboard</a>
    <section class="fs-card mt-4 overflow-hidden">
        <div class="border-b border-surface-border p-5">
            <h1 class="text-xl font-semibold">Active products</h1>
            <p class="mt-2 text-sm text-text-muted">Current listings and inventory. Variant products show the sum of active variant stock.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-surface-muted text-xs text-text-muted"><tr><th class="px-4 py-3">Product</th><th class="px-4 py-3">Shop / category</th><th class="px-4 py-3">Variants / SKU</th><th class="px-4 py-3 text-right">Available stock</th></tr></thead>
                <tbody class="divide-y divide-surface-border/40">
                    @forelse($products as $product)
                        <tr>
                            <th class="px-4 py-4 font-medium">{{ $product->name }}<span class="mt-1 block text-xs font-normal text-text-muted">Product ID {{ $product->id }}</span></th>
                            <td class="px-4 py-4">{{ $product->shop?->name ?? 'Unavailable shop' }}<span class="mt-1 block text-xs text-text-muted">{{ $product->category?->name ?? 'No category' }}</span></td>
                            <td class="px-4 py-4">
                                @if($product->has_variants)
                                    <ul class="space-y-1">
                                        @foreach($product->variants as $variant)
                                            <li><span class="font-medium">{{ collect($variant->options ?? [])->map(fn ($value, $name) => $name.': '.$value)->join(', ') ?: 'Option' }}</span><span class="block text-xs text-text-muted">{{ $variant->sku }} · {{ $variant->stock }} in stock</span></li>
                                        @endforeach
                                    </ul>
                                @else
                                    {{ $product->sku ?: 'No SKU' }}
                                @endif
                            </td>
                            <td class="px-4 py-4 text-right font-semibold tabular-nums">{{ number_format($product->availableStock()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-text-muted">No active products.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-surface-border p-4">{{ $products->links() }}</div>
    </section>
@endsection
