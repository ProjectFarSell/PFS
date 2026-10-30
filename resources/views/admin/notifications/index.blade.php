@extends('layouts.portal')

@section('title', 'Notification deliveries · FarSell')

@section('content')
    <section class="fs-card-raised p-5">
        <h1 class="text-2xl font-semibold">Notification deliveries</h1>
        <p class="mt-2 text-sm text-text-muted">Track order and delivery messages, automatic retries, and failures.</p>
        <p class="mt-2 text-xs text-text-muted">Current mailer: <strong>{{ config('notifications.mailer') }}</strong> · Queue: {{ config('notifications.queue') }}. Log and Mailpit deliveries are for testing. Sent means the selected mailer accepted the message.</p>
        @unless(config('notifications.enabled'))<p class="mt-3 text-sm text-error">New order notifications are currently disabled.</p>@endunless
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach(\App\Models\NotificationDelivery::STATUSES as $state)
                <a href="{{ route('admin.notifications.index', ['status' => $state]) }}" class="btn-outline text-xs">{{ ucfirst($state) }}: {{ $counts[$state] ?? 0 }}</a>
            @endforeach
        </div>
        <form method="get" class="mt-4 flex flex-wrap items-end gap-3">
            <div><label for="notification-status" class="fs-label">Status</label><select id="notification-status" name="status" class="fs-input"><option value="">All statuses</option>@foreach(\App\Models\NotificationDelivery::STATUSES as $state)<option value="{{ $state }}" @selected(request('status') === $state)>{{ ucfirst($state) }}</option>@endforeach</select></div>
            <div><label for="notification-type" class="fs-label">Message type</label><select id="notification-type" name="type" class="fs-input"><option value="">All types</option>@foreach(\App\Models\NotificationDelivery::TYPES as $type => $label)<option value="{{ $type }}" @selected(request('type') === $type)>{{ $label }}</option>@endforeach</select></div>
            <button class="btn-accent">Filter</button>
            <a href="{{ route('admin.notifications.index') }}" class="btn-outline">Clear</a>
        </form>
    </section>

    <section class="mt-5 overflow-x-auto rounded-xl border border-surface-border bg-surface">
        <table class="w-full min-w-[65rem] text-left text-sm">
            <thead><tr class="border-b border-surface-border text-text-muted"><th class="p-3">Message / order</th><th class="p-3">Recipient</th><th class="p-3">Status</th><th class="p-3">Attempts</th><th class="p-3">Delivery timing</th><th class="p-3">Details</th><th class="p-3">Action</th></tr></thead>
            <tbody>
                @forelse($deliveries as $delivery)
                    <tr class="border-b border-surface-border align-top last:border-0">
                        <td class="p-3"><p class="font-medium">{{ \App\Models\NotificationDelivery::TYPES[$delivery->type] ?? $delivery->type }}</p>@if($delivery->order)<a href="{{ route('orders.show', $delivery->order_id) }}" class="mt-1 inline-block text-accent underline">{{ $delivery->order->number }}</a>@endif<p class="mt-1 text-xs text-text-muted">Created {{ $delivery->created_at->format('M j, Y g:i A') }}</p></td>
                        <td class="p-3"><p>{{ $delivery->recipient_name }}</p><p class="mt-1 break-all text-xs text-text-muted">{{ $delivery->recipient_email }}</p></td>
                        <td class="p-3"><span class="badge {{ $delivery->status === 'sent' ? 'badge-success' : 'badge-neutral' }}">{{ ucfirst($delivery->status) }}</span><p class="mt-1 text-xs text-text-muted">{{ $delivery->mailer ?? 'Awaiting worker' }}</p></td>
                        <td class="p-3">{{ $delivery->attempts }}</td>
                        <td class="p-3 text-xs">
                            @if($delivery->last_attempt_at)<p>Last attempt: {{ $delivery->last_attempt_at->format('M j, g:i:s A') }}</p>@endif
                            @if($delivery->next_retry_at)<p class="mt-1">Retry due: {{ $delivery->next_retry_at->format('M j, g:i:s A') }}</p>@endif
                            @if($delivery->sent_at)<p class="mt-1">Sent: {{ $delivery->sent_at->format('M j, g:i:s A') }}</p>@endif
                            @if(!$delivery->last_attempt_at)<p>Waiting for the notification worker.</p>@endif
                        </td>
                        <td class="max-w-xs p-3 text-xs text-text-muted">{{ $delivery->last_error ?? '—' }}</td>
                        <td class="p-3">@if(in_array($delivery->status, ['failed', 'pending'], true))<form method="post" action="{{ route('admin.notifications.retry', $delivery) }}">@csrf<button class="btn-outline whitespace-nowrap">Retry delivery</button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-6 text-center text-text-muted">No notifications match this view. New order and shipment events will appear here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <div class="mt-4">{{ $deliveries->links() }}</div>
@endsection
