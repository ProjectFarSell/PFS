@if(auth()->user()?->role === \App\Enums\UserRole::Buyer)
@php($notificationUnread = \App\Models\BuyerNotification::query()->where('user_id', auth()->id())->whereHas('order', fn ($q) => $q->where('user_id', auth()->id()))->whereNull('read_at')->count())
<x-ui.icon-button :href="route('account.notifications.index')" class="relative shrink-0"
   label="Notifications" x-data="{ count: {{ $notificationUnread }}, timer: null,
       init() { this.timer = setInterval(() => { if (!document.hidden) fetch('{{ route('account.notifications.unread') }}', {headers: {'Accept': 'application/json'}}).then(r => r.ok ? r.json() : null).then(data => { if (data) this.count = data.unread; }).catch(() => {}); }, 30000); },
       destroy() { clearInterval(this.timer); } }">
    <x-ui.icon name="notification" />
    <span x-show="count > 0" x-text="count > 99 ? '99+' : count" class="absolute -right-1 -top-1 rounded-full bg-accent px-1 text-[10px] text-white" aria-live="polite">{{ $notificationUnread }}</span>
</x-ui.icon-button>
@endif
