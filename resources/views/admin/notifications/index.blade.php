@extends('layouts.portal')

@section('title', 'Notification deliveries · FarSell')

@section('content')
    <section class="fs-card-raised mb-5 p-5">
        <h2 class="text-lg font-semibold">Mobile delivery monitor</h2>
        <p class="mt-2 text-sm text-text-muted">Push: {{ $mobileHealth['push_enabled'] ? 'enabled' : 'not enabled' }} · Real-time: {{ $mobileHealth['realtime_enabled'] ? 'enabled' : 'not enabled' }} · Pending events: {{ $mobileHealth['pending'] }} · Failed: {{ $mobileHealth['failed'] }}</p>
        <p class="mt-2 text-xs text-text-muted">Oldest pending: {{ $mobileHealth['oldest_pending_at'] ?? 'none' }}. Worker heartbeat: {{ $mobileHealth['worker_last_seen'] ? \Illuminate\Support\Carbon::createFromTimestamp($mobileHealth['worker_last_seen'])->diffForHumans() : 'not observed' }}. Provider acceptance does not guarantee device delivery. Reload to refresh.</p>
    </section>
    <section class="fs-card-raised p-5">
        <h1 class="text-2xl font-semibold">Notification deliveries</h1>
        <p class="mt-2 text-sm text-text-muted">Track order and delivery messages, automatic retries, and failures.</p>
        <p class="mt-2 text-xs text-text-muted">Current mailer: <strong>{{ config('notifications.mailer') }}</strong> · Queue: {{ config('notifications.queue') }}. Log and Mailpit deliveries are for testing. Sent means the selected mailer accepted the message.</p>
        @unless(config('notifications.enabled'))<p class="mt-3 text-sm text-error">New order notifications are currently disabled.</p>@endunless
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <x-ui.button :href="route('admin.notifications.index', [...request()->only('type'), 'status' => 'failed'])" variant="primary" size="lg" :aria-current="request('status') === 'failed' ? 'page' : null">Failed: {{ $counts['failed'] ?? 0 }}</x-ui.button>
            <x-ui.button :href="route('admin.notifications.index', [...request()->only('type'), 'status' => 'retrying'])" :aria-current="request('status') === 'retrying' ? 'page' : null">Retrying: {{ $counts['retrying'] ?? 0 }}</x-ui.button>
            <x-action-menu label="Filters" context="for notification deliveries" class="ml-auto">
                <form method="get" class="space-y-3 p-2">
                    <div><label for="notification-status" class="fs-label">Status</label><select id="notification-status" name="status" class="fs-input w-full"><option value="">All statuses</option>@foreach(\App\Models\NotificationDelivery::STATUSES as $state)<option value="{{ $state }}" @selected(request('status') === $state)>{{ ucfirst($state) }} ({{ $counts[$state] ?? 0 }})</option>@endforeach</select></div>
                    <div><label for="notification-type" class="fs-label">Message type</label><select id="notification-type" name="type" class="fs-input w-full"><option value="">All types</option>@foreach(\App\Models\NotificationDelivery::TYPES as $type => $label)<option value="{{ $type }}" @selected(request('type') === $type)>{{ $label }}</option>@endforeach</select></div>
                    <x-ui.button variant="primary" size="lg" type="submit" class="w-full">Apply filters</x-ui.button>
                </form>
            </x-action-menu>
        </div>
        @if(request()->filled('status') || request()->filled('type'))
            <div class="mt-3 flex flex-wrap items-center gap-2" aria-label="Active filters">
                @if(request()->filled('status'))
                    <x-ui.button variant="link" :href="route('admin.notifications.index', request()->only('type'))" class="bg-surface-muted" aria-label="Remove status filter">Status: {{ ucfirst(request('status')) }} <x-ui.icon name="close" size="sm" class="ml-2" /></x-ui.button>
                @endif
                @if(request()->filled('type'))
                    <x-ui.button variant="link" :href="route('admin.notifications.index', request()->only('status'))" class="bg-surface-muted" aria-label="Remove message type filter">Type: {{ \App\Models\NotificationDelivery::TYPES[request('type')] }} <x-ui.icon name="close" size="sm" class="ml-2" /></x-ui.button>
                @endif
            </div>
        @endif
    </section>

    <section class="mt-5 overflow-x-auto rounded-xl border border-surface-border bg-surface">
        <table class="w-full min-w-[65rem] text-left text-sm">
            <thead><tr class="border-b border-surface-border text-text-muted"><th class="p-3">Message / order</th><th class="p-3">Recipient</th><th class="p-3">Status</th><th class="p-3">Attempts</th><th class="p-3">Delivery timing</th><th class="p-3">Details</th><th class="p-3">Action</th></tr></thead>
            <tbody>
                @forelse($deliveries as $delivery)
                    <tr class="relative border-b border-surface-border align-top last:border-0">
                        <td class="p-3"><p class="font-medium">{{ \App\Models\NotificationDelivery::TYPES[$delivery->type] ?? $delivery->type }}</p>@if($delivery->order)<p class="mt-1">{{ $delivery->order->number }}</p>@endif<p class="mt-1 text-xs text-text-muted">Created {{ $delivery->created_at->format('M j, Y g:i A') }}</p></td>
                        <td class="p-3"><p>{{ $delivery->recipient_name }}</p><p class="mt-1 break-all text-xs text-text-muted">{{ $delivery->recipient_email }}</p></td>
                        <td class="p-3"><x-ui.status-pill :status="$delivery->status">{{ ucfirst($delivery->status) }}</x-ui.status-pill><p class="mt-1 text-xs text-text-muted">{{ $delivery->mailer ?? 'Awaiting worker' }}</p></td>
                        <td class="p-3">{{ $delivery->attempts }}</td>
                        <td class="p-3 text-xs">
                            @if($delivery->last_attempt_at)<p>Last attempt: {{ $delivery->last_attempt_at->format('M j, g:i:s A') }}</p>@endif
                            @if($delivery->next_retry_at)<p class="mt-1">Retry due: {{ $delivery->next_retry_at->format('M j, g:i:s A') }}</p>@endif
                            @if($delivery->sent_at)<p class="mt-1">Sent: {{ $delivery->sent_at->format('M j, g:i:s A') }}</p>@endif
                            @if(!$delivery->last_attempt_at)<p>Waiting for the notification worker.</p>@endif
                        </td>
                        <td class="max-w-xs p-3 text-xs text-text-muted">{{ $delivery->last_error ?? '—' }}</td>
                        <td class="p-3">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if($delivery->order)<x-ui.button variant="link" :href="route('orders.show', $delivery->order_id)" class="whitespace-nowrap after:absolute after:inset-0 after:content-['']" aria-label="View order {{ $delivery->order->number }}">View order</x-ui.button>@endif
                                @if(in_array($delivery->status, ['failed', 'pending'], true))<form method="post" action="{{ route('admin.notifications.retry', $delivery) }}" class="relative z-10">@csrf<x-ui.button variant="secondary" type="submit" class="whitespace-nowrap">Retry delivery</x-ui.button></form>@endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-6 text-center text-text-muted">No notifications match this view. New order and shipment events will appear here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <div class="mt-4">{{ $deliveries->links() }}</div>
@endsection
