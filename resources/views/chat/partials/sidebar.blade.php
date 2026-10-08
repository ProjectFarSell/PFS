<aside class="{{ $activeConversation ? 'hidden md:block' : '' }} {{ $activeConversation ? '' : 'min-h-[68vh]' }} border-b border-surface-border md:border-b-0" x-data="{ search: '' }">
    <div class="flex items-center gap-3 border-b border-surface-border p-3">
        <label class="relative min-w-0 flex-1">
            <span class="sr-only">Search conversations</span>
            <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>
            <input type="search" x-model="search" placeholder="Search name" class="fs-input w-full py-2 pl-9 text-sm">
        </label>
        <span class="shrink-0 text-sm text-text-muted">All</span>
    </div>
    <nav aria-label="Conversations" class="max-h-[calc(68vh-58px)] divide-y divide-surface-border overflow-y-auto">
        @forelse($conversations as $item)
            @php
                $counterpart = $isSeller ? $item->buyer->name : $item->shop->name;
                $preview = $item->latestMessage?->body ?? ($item->product?->name ? '[Product] '.$item->product->name : 'Start a conversation');
                $searchText = mb_strtolower($counterpart.' '.$preview.' '.($item->product?->name ?? ''));
                $lastActivity = $item->latestMessage?->created_at ?? $item->updated_at;
            @endphp
            <a href="{{ route('chat.show', $item) }}" data-search="{{ $searchText }}" x-show="!search || $el.dataset.search.includes(search.toLowerCase())" class="flex min-h-[76px] items-center gap-3 px-4 py-3 transition-colors hover:bg-surface-muted {{ $activeConversation?->id === $item->id ? 'bg-surface-muted' : '' }}">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-accent-subtle text-sm font-semibold text-accent">{{ mb_strtoupper(mb_substr($counterpart, 0, 1)) }}</span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center justify-between gap-2">
                        <span class="truncate text-sm font-semibold">{{ $counterpart }}</span>
                        <time class="shrink-0 text-[11px] text-text-muted">{{ $lastActivity->isToday() ? $lastActivity->format('H:i') : $lastActivity->format('M j') }}</time>
                    </span>
                    <span class="mt-1 flex items-center justify-between gap-2">
                        <span class="truncate text-sm text-text-muted">{{ $preview }}</span>
                        @if($item->unread_count)<span class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-accent px-1.5 text-[11px] font-semibold text-white">{{ $item->unread_count > 99 ? '99+' : $item->unread_count }}</span>@endif
                    </span>
                </span>
            </a>
        @empty
            <p class="p-5 text-sm text-text-muted">No conversations yet. Start one from a product page.</p>
        @endforelse
    </nav>
</aside>
