@extends('layouts.portal')

@section('title', 'Admin Dashboard · FarSell')

@section('content')
    <a href="{{ route('fulfillments.index', ['status' => 'ready']) }}" class="btn-accent mb-4">Dispatch shipments</a>
    <section class="rounded-2xl bg-stone-900 p-5 text-white sm:p-7">
        <p class="text-xs font-semibold uppercase tracking-widest text-violet-300">FarSell administration</p>
        <h1 class="mt-2 text-2xl font-semibold">Marketplace overview</h1>
        <p class="mt-2 text-sm text-stone-300">Welcome, {{ auth()->user()->name }}. Here is the current activity across your marketplace.</p>
        <p class="mt-4 text-xs text-stone-300">Read-only overview · figures reflect current database records, including demo data.</p>
    </section>

    <dl class="my-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach (['buyers' => 'Buyer accounts', 'products' => 'Active products', 'orders' => 'Total orders', 'pendingRiders' => 'Pending rider applications'] as $key => $label)
            <div class="rounded-xl border border-surface-border bg-surface p-4">
                <dt class="text-xs text-text-muted">{{ $label }}</dt>
                <dd class="mt-2 text-2xl font-semibold">{{ number_format($stats[$key]) }}</dd>
            </div>
        @endforeach
    </dl>

    <section class="fs-card mb-5 flex flex-wrap items-center justify-between gap-3 p-4">
        <div><h2 class="font-semibold">Seller applications</h2><p class="mt-1 text-sm text-text-muted">{{ $pendingSellers }} pending review. Approve applicants to create their shops and enable listings.</p></div>
        <a href="{{ route('admin.sellers.index') }}" class="btn-accent">Review seller applications</a>
    </section>

    <section class="fs-card mb-5 flex flex-wrap items-center justify-between gap-3 p-4">
        <div>
            <h2 class="font-semibold">Rider applications</h2>
            <p class="mt-1 text-sm text-text-muted">{{ $stats['pendingRiders'] }} pending review. Review details before granting rider access.</p>
        </div>
        <a href="{{ route('admin.riders.index') }}" class="btn-accent">Review rider applications</a>
    </section>

    <section aria-labelledby="recent-orders" class="rounded-xl border border-surface-border bg-surface">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-surface-border p-4">
            <div>
                <h2 id="recent-orders" class="font-semibold">Orders</h2>
                <p class="mt-1 text-xs text-text-muted">Newest first · {{ number_format($orders->total()) }} {{ $selectedStatus ? 'matching' : 'total' }} orders</p>
            </div>
            <form action="{{ route('admin.dashboard') }}" method="get" class="flex flex-wrap items-center gap-2">
                <label for="order-status" class="text-sm">Status</label>
                <select id="order-status" name="status" class="rounded-lg border-surface-border text-sm">
                    <option value="">All statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white">Filter</button>
                @if($selectedStatus)
                    <a href="{{ route('admin.dashboard') }}" class="text-sm text-accent">Clear</a>
                @endif
            </form>
        </div>
        @if($errors->has('status'))
            <p role="alert" class="px-4 pt-3 text-sm text-red-600">{{ $errors->first('status') }}</p>
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
                <tbody class="divide-y divide-surface-border">
                    @forelse($orders as $order)
                        <tr>
                            <th scope="row" class="px-4 py-3 font-medium">
                                {{ $order->number }}
                                <time datetime="{{ $order->created_at->toIso8601String() }}" class="mt-1 block whitespace-nowrap text-xs font-normal text-text-muted">{{ $order->created_at->format('M j, Y · g:i A') }}</time>
                            </th>
                            <td class="px-4 py-3 break-words">{{ $order->buyer?->name ?? 'Unavailable account' }}</td>
                            <td class="px-4 py-3"><span class="inline-block rounded-full bg-surface-muted px-2 py-1 text-xs">{{ $order->status->label() }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-medium">₱{{ number_format((float) $order->total, 2) }}</td>
                            <td class="px-4 py-3"><a href="{{ route('orders.show', $order) }}" aria-label="View order {{ $order->number }}" class="text-accent underline">View</a></td>
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

    <section aria-labelledby="low-stock" class="mt-5 rounded-xl border border-surface-border bg-surface p-4">
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
                    <span class="shrink-0 text-sm font-semibold {{ $product->stock <= 0 ? 'text-red-600' : 'text-text-base' }}">{{ $product->stock <= 0 ? 'Out of stock' : $product->stock.' left' }}</span>
                </li>
            @empty
                <li class="py-4 text-sm text-text-muted">No active products are low on stock.</li>
            @endforelse
        </ul>
    </section>
@endsection
