@extends('layouts.portal')

@section('title', 'Admin Dashboard · FarSell')

@section('content')
    <section class="flex flex-wrap items-end justify-between gap-5 pb-6">
        <div>
            <p class="section-eyebrow">FarSell administration</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight">Marketplace overview</h1>
            <p class="mt-2 text-sm text-text-muted">Welcome, {{ auth()->user()->name }}. Here is what needs your attention.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button variant="primary" size="lg" :href="route('fulfillments.index', ['status' => 'ready'])">Dispatch shipments</x-ui.button>
            <x-ui.button variant="secondary" :href="route('admin.logistics.index')">Logistics</x-ui.button>
            <x-action-menu class="ml-auto" context="administration actions">
                <p class="px-4 text-xs font-semibold text-text-muted">Delivery exceptions</p>
                <a href="{{ route('fulfillments.index', ['status' => 'delivery_failed']) }}" class="dropdown-item">Failed deliveries</a>
                <a href="{{ route('fulfillments.index', ['status' => 'returning']) }}" class="dropdown-item">Returns to seller</a>
                <p class="border-t border-surface-border px-4 pt-2 text-xs font-semibold text-text-muted">After-sales and finance</p>
                <a href="{{ route('returns.index') }}" class="dropdown-item">Returns & refunds</a>
                <a href="{{ route('replacements.index') }}" class="dropdown-item">Replacement requests</a>
                <a href="{{ route('settlements.index') }}" class="dropdown-item">Commissions & settlements</a>
                <p class="border-t border-surface-border px-4 pt-2 text-xs font-semibold text-text-muted">Administration</p>
                <a href="{{ route('admin.operations') }}" class="dropdown-item">Operations</a>
                <a href="{{ route('admin.categories.index') }}" class="dropdown-item">Manage categories</a>
                <a href="{{ route('admin.security.accounts') }}" class="dropdown-item">Account protection</a>
                <a href="{{ route('admin.security.audit') }}" class="dropdown-item">Audit trail</a>
                <a href="{{ route('admin.privacy-requests.index') }}" class="dropdown-item">Privacy requests</a>
            </x-action-menu>
        </div>
    </section>

    @php($statCards = [
        'buyers' => ['Buyer accounts', 'People in your marketplace', route('admin.buyers.index'), 'buyers'],
        'products' => ['Active products', 'Browse enabled listings', route('admin.products.index'), 'product'],
        'orders' => ['Total orders', 'Across all order statuses', route('admin.dashboard').'#recent-orders', 'order'],
        'pendingRiders' => ['Pending rider applications', 'Awaiting your review', route('admin.riders.index', ['status' => 'pending']), 'time'],
    ])
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" data-admin-dashboard data-summary-url="{{ route('admin.dashboard.summary') }}">
        @foreach($statCards as $key => [$label, $description, $url, $icon])
            <a href="{{ $url }}" class="admin-stat group block cursor-pointer rounded-2xl transition hover:border-accent/50 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent" data-admin-stat-label="{{ $label }}" data-admin-stat-description="{{ $description }}" aria-label="{{ $label }}: {{ number_format($stats[$key]) }}. {{ $description }}">
                <span class="flex items-start justify-between gap-2 text-xs font-medium text-text-muted">{{ $label }}<span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-accent-subtle text-accent"><x-ui.icon :name="$icon" size="sm" /></span></span>
                <span class="mt-2 block text-3xl font-bold tracking-tight tabular-nums" data-admin-stat-value="{{ $key }}">{{ number_format($stats[$key]) }}</span>
                <span class="mt-2 block text-xs text-text-muted">{{ $description }}</span>
            </a>
        @endforeach
    </div>

    <div class="my-7 grid gap-4 md:grid-cols-2">
    <section class="admin-action">
        <div class="flex items-center justify-between gap-3"><h2 class="font-semibold">Seller applications</h2><span class="badge badge-accent tabular-nums">{{ $pendingSellers }} pending</span></div>
        <p class="text-sm text-text-muted">Review applicants before opening their shops and enabling listings.</p>
        <x-ui.button variant="secondary" :href="route('admin.sellers.index')" class="mt-auto self-start">Review seller applications <x-ui.icon name="next" size="sm" /></x-ui.button>
    </section>

    <section class="admin-action">
        <div class="flex items-center justify-between gap-3"><h2 class="font-semibold">Rider applications</h2><span class="badge badge-accent tabular-nums">{{ $stats['pendingRiders'] }} pending</span></div>
        <p class="text-sm text-text-muted">Check delivery partners’ details before granting rider access.</p>
        <x-ui.button variant="secondary" :href="route('admin.riders.index')" class="mt-auto self-start">Review rider applications <x-ui.icon name="next" size="sm" /></x-ui.button>
    </section>
    </div>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
    <section aria-labelledby="recent-orders" class="fs-card min-w-0 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-surface-border/40 p-5">
            <div>
                <h2 id="recent-orders" class="scroll-mt-32 font-semibold">Orders</h2>
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
                <x-ui.button variant="secondary" type="submit" class="min-h-11">Filter</x-ui.button>
                @if($selectedStatus)
                    <x-ui.button variant="link" :href="route('admin.dashboard')" class="text-sm text-accent">Clear</x-ui.button>
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
                        <tr class="admin-row relative">
                            <th scope="row" class="px-4 py-3 font-medium">
                                {{ $order->number }}
                                <time datetime="{{ $order->created_at->toIso8601String() }}" class="mt-1 block whitespace-nowrap text-xs font-normal text-text-muted">{{ $order->created_at->format('M j, Y · g:i A') }}</time>
                            </th>
                            <td class="px-4 py-3 break-words">{{ $order->buyer?->name ?? 'Unavailable account' }}</td>
                            <td class="px-4 py-3"><x-ui.status-pill :status="$order->status->value" class="whitespace-nowrap">{{ $order->status->label() }}</x-ui.status-pill></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums">₱{{ number_format((float) $order->total, 2) }}</td>
                            <td class="px-4 py-3 text-right"><x-ui.button variant="link" :href="route('orders.show', $order)" aria-label="View order {{ $order->number }}" class="after:absolute after:inset-0 after:content-['']">View <x-ui.icon name="next" size="sm" class="ml-1" /></x-ui.button></td>
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

    <section aria-labelledby="low-stock" class="fs-card p-5" data-low-stock-panel>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="low-stock" class="font-semibold">Inventory attention</h2>
            <span class="rounded-full bg-accent-subtle px-3 py-1 text-xs text-accent"><span data-low-stock-count>{{ $lowStockCount }}</span> low-stock products</span>
        </div>
        <p class="mt-1 text-xs text-text-muted">Includes available stock across active variants. Refreshes every 10 seconds.</p>
        <p class="mt-1 text-[11px] text-text-muted" data-inventory-updated aria-live="polite">Showing current database stock.</p>
        <ul class="mt-3 divide-y divide-surface-border" data-low-stock-list aria-live="polite">
            @forelse($lowStockProducts as $product)
                <li class="flex items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <a href="{{ route('admin.products.index') }}" class="break-words text-sm font-medium text-accent underline">{{ $product->name }}</a>
                        <p class="mt-1 text-xs text-text-muted">{{ $product->shop?->name ?? 'Unavailable shop' }}</p>
                    </div>
                    <span class="shrink-0 text-xs font-semibold tabular-nums {{ $product->availableStock() <= 0 ? 'text-error' : 'text-text-base' }}">{{ $product->availableStock() <= 0 ? 'Out of stock' : $product->availableStock().' left' }}</span>
                </li>
            @empty
                <li class="py-4 text-sm text-text-muted">No active products are low on stock.</li>
            @endforelse
        </ul>
    </section>
    </div>
    <p class="mt-5 text-xs text-text-muted">Figures reflect current marketplace records.</p>
@endsection

@push('scripts')
<script src="{{ asset('js/admin-dashboard-live.js') }}" defer></script>
@endpush
