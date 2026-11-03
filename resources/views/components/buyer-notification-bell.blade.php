@if(auth()->user()?->role === \App\Enums\UserRole::Buyer)
@php($notificationUnread = \App\Models\BuyerNotification::query()->where('user_id', auth()->id())->whereHas('order', fn ($q) => $q->where('user_id', auth()->id()))->whereNull('read_at')->count())
<a href="{{ route('account.notifications.index') }}" class="theme-toggle relative shrink-0"
   aria-label="Notifications" x-data="{ count: {{ $notificationUnread }}, timer: null,
       init() { this.timer = setInterval(() => { if (!document.hidden) fetch('{{ route('account.notifications.unread') }}', {headers: {'Accept': 'application/json'}}).then(r => r.ok ? r.json() : null).then(data => { if (data) this.count = data.unread; }).catch(() => {}); }, 30000); },
       destroy() { clearInterval(this.timer); } }">
    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
    <span x-show="count > 0" x-text="count > 99 ? '99+' : count" class="absolute -right-1 -top-1 rounded-full bg-accent px-1 text-[10px] text-white" aria-live="polite">{{ $notificationUnread }}</span>
</a>
@endif
