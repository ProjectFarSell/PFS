@extends('layouts.app')
@section('title', 'Notifications - FarSell')
@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Notifications</h1>
        <form method="post" action="{{ route('account.notifications.read-all') }}">@csrf<button class="btn-outline">Mark all as read</button></form>
    </div>
    <p class="text-sm text-text-muted">Your order and delivery updates. Open an update to view its order.</p>
    @forelse($notifications as $notification)
        <article class="rounded-xl border border-surface-border bg-surface p-5 {{ $notification->read_at ? '' : 'border-l-4 border-l-accent' }}">
            <div class="flex justify-between gap-3"><h2 class="font-semibold">{{ $notification->title }}</h2>@unless($notification->read_at)<span class="text-xs text-accent">Unread</span>@endunless</div>
            <p class="mt-2 text-sm">{{ $notification->message }}</p>
            <p class="my-3 text-xs text-text-muted">Order {{ $notification->order->number }} · {{ $notification->created_at->format('M j, Y g:i A') }}</p>
            <form method="post" action="{{ route('account.notifications.open', $notification) }}">@csrf<button class="text-sm font-semibold text-accent hover:underline">View order</button></form>
        </article>
    @empty
        <div class="rounded-xl border border-surface-border p-6">No notifications yet. Updates will appear when you place an order.</div>
    @endforelse
    {{ $notifications->links() }}
</div>
@endsection
