@extends('layouts.portal')

@section('title', 'Admin Dashboard · FarSell')

@section('content')
    <section class="flex flex-wrap items-end justify-between gap-5 pb-6">
        <div>
            <p class="section-eyebrow">FarSell administration</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight">Marketplace overview</h1>
            <p class="mt-2 text-sm text-text-muted">Welcome, {{ auth()->user()->name }}. Here is what needs your attention.</p>
        </div>
        <a href="{{ route('fulfillments.index', ['status' => 'ready']) }}" class="btn-accent min-h-11">Dispatch shipments <span aria-hidden="true">→</span></a>
    </section>

    <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach (['buyers' => ['Buyer accounts', 'People in your marketplace', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8m8 0a4 4 0 0 0 0-8m5 18v-2a4 4 0 0 0-3-3.87'], 'products' => ['Active products', 'Currently enabled listings', 'm3 7 9-5 9 5v10l-9 5-9-5Zm0 0 9 5 9-5M12 12v10'], 'orders' => ['Total orders', 'Across all order statuses', 'M5 3h14v18H5Zm4 5h6m-6 4h6m-6 4h4'], 'pendingRiders' => ['Pending rider applications', 'Awaiting your review', 'M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0'] ] as $key => [$label, $description, $path])
            <div class="admin-stat">
                <dt class="flex items-start justify-between gap-2 text-xs font-medium text-text-muted">{{ $label }}<span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-accent-subtle text-accent"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}"/></svg></span></dt>
                <dd class="mt-2 text-3xl font-bold tracking-tight tabular-nums">{{ number_format($stats[$key]) }}<span class="mt-2 block text-xs font-normal tracking-normal text-text-muted">{{ $description }}</span></dd>
            </div>
        @endforeach
    </dl>

    <div class="my-7 grid gap-4 md:grid-cols-2">
    <section class="admin-action">
        <div class="flex items-center justify-between gap-3"><h2 class="font-semibold">Seller applications</h2><span class="badge badge-accent tabular-nums">{{ $pendingSellers }} pending</span></div>
        <p class="text-sm text-text-muted">Review applicants before opening their shops and enabling listings.</p>
        <a href="{{ route('admin.sellers.index') }}" class="btn-outline mt-auto self-start">Review seller applications <span aria-hidden="true">→</span></a>
    </section>

    <section class="admin-action">
        <div class="flex items-center justify-between gap-3"><h2 class="font-semibold">Rider applications</h2><span class="badge badge-accent tabular-nums">{{ $stats['pendingRiders'] }} pending</span></div>
        <p class="text-sm text-text-muted">Check delivery partners’ details before granting rider access.</p>
        <a href="{{ route('admin.riders.index') }}" class="btn-outline mt-auto self-start">Review rider applications <span aria-hidden="true">→</span></a>
    </section>
    </div>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
    <section aria-labelledby="recent-orders" class="fs-card min-w-0 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-surface-border/40 p-5">
            <div>
                <h2 id="recent-orders" class="font-semibold">Orders</h2>
                <p class="mt-1 text-xs text-text-muted">Newest first · {{ number_format($orders->total()) }} {{ $selectedStatus ? 'matching' : 'total' }} orders</p>
            </div>
            <form action="{{ route('admin.dashboard') }}" method="get" class="flex flex-wrap items-center gap-2">
                <label for="order-status" class="text-sm">Status</label>
                <select id="order-status" name="status" class="fs-input min-h-11 w-auto max-w-full">
                    <option value="">All statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn-outline min-h-11 px-4">Filter</button>
                @if($selectedStatus)
                    <a href="{{ route('admin.dashboard') }}" class="text-sm text-accent">Clear</a>
                @endif
            </form>
        </div>
        @if($errors->has('status'))
            <p role="alert" class="px-4 pt-3 text-sm text-error">{{ $errors->first('status') }}</p>
        @endif
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Marketplace orders, newest first</caption>
                <thead class="bg-surface-muted text-xs text-text-muted">
                    <tr>
                        <th scope="col" class="px-4 py-3">Order / date</th>
                        <th scope="col" class="px-4 py-3">Buyer</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="px-4 py-3 text-right">Total</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Details</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-border/40">
                    @forelse($orders as $order)
                        <tr class="admin-row">
                            <th scope="row" class="px-4 py-3 font-medium">
                                {{ $order->number }}
                                <time datetime="{{ $order->created_at->toIso8601String() }}" class="mt-1 block whitespace-nowrap text-xs font-normal text-text-muted">{{ $order->created_at->format('M j, Y · g:i A') }}</time>
                            </th>
                            <td class="px-4 py-3 break-words">{{ $order->buyer?->name ?? 'Unavailable account' }}</td>
                            <td class="px-4 py-3"><span class="badge badge-neutral whitespace-nowrap">{{ $order->status->label() }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums">₱{{ number_format((float) $order->total, 2) }}</td>
                            <td class="px-4 py-3"><a href="{{ route('orders.show', $order) }}" aria-label="View order {{ $order->number }}" class="inline-flex min-h-11 items-center font-semibold text-accent hover:underline">View <span aria-hidden="true" class="ml-1">→</span></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-text-muted">{{ $selectedStatus ? 'No orders match this status.' : 'No orders have been placed yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
            <div class="border-t border-surface-border p-4">{{ $orders->links() }}</div>
        @endif
    </section>

    <section aria-labelledby="low-stock" class="fs-card p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="low-stock" class="font-semibold">Inventory attention</h2>
            <span class="rounded-full bg-accent-subtle px-3 py-1 text-xs text-accent">{{ $lowStockCount }} low-stock products</span>
        </div>
        <p class="mt-1 text-xs text-text-muted">Active products with 5 or fewer units of product-level stock. Showing up to 5, lowest stock first.</p>
        <ul class="mt-3 divide-y divide-surface-border">
            @forelse($lowStockProducts as $product)
                <li class="flex items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <a href="{{ route('products.show', $product) }}" class="break-words text-sm font-medium text-accent underline">{{ $product->name }}</a>
                        <p class="mt-1 text-xs text-text-muted">{{ $product->shop?->name ?? 'Unavailable shop' }}</p>
                    </div>
                    <span class="shrink-0 text-xs font-semibold tabular-nums {{ $product->stock <= 0 ? 'text-error' : 'text-text-base' }}">{{ $product->stock <= 0 ? 'Out of stock' : $product->stock.' left' }}</span>
                </li>
            @empty
                <li class="py-4 text-sm text-text-muted">No active products are low on stock.</li>
            @endforelse
        </ul>
    </section>
    </div>
    <p class="mt-5 text-xs text-text-muted">Figures reflect current database records, including demo data.</p>
@endsection
