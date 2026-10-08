@php
    $dockUser = auth()->user();
    $canUseChatDock = $dockUser && in_array($dockUser->role, [\App\Enums\UserRole::Buyer, \App\Enums\UserRole::Seller], true);
@endphp
<div class="fixed bottom-4 right-4 z-[70]" x-data="chatDock(@js(route('chat.widget.conversations')), @js(url('/chat')), @js($canUseChatDock), {{ (int) ($chatUnreadCount ?? 0) }}, {{ (int) ($dockUser?->id ?? 0) }})" x-init="init()" x-on:beforeunload.window="stopPolling()">
    <section x-show="open" x-cloak x-transition class="mb-3 flex h-[min(34rem,calc(100vh-7rem))] w-[min(23rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-surface-border bg-surface shadow-2xl" aria-label="Chat panel">
        <header class="flex min-h-14 items-center justify-between gap-3 border-b border-surface-border px-4">
            <div class="flex min-w-0 items-center gap-2">
                <template x-if="selected"><button type="button" @click="backToList()" class="shrink-0 rounded-full p-1 text-text-muted hover:bg-surface-muted" aria-label="Back to conversations">←</button></template>
                <h2 class="truncate font-semibold text-accent" x-text="selected ? selected.name : 'Chat'"></h2>
                <span x-show="!selected && unreadTotal > 0" class="rounded-full bg-accent px-2 py-0.5 text-[10px] font-semibold text-white" x-text="unreadTotal > 99 ? '99+' : unreadTotal"></span>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <a href="{{ route('chat.index') }}" class="rounded-full px-2 py-1 text-xs text-text-muted hover:bg-surface-muted">Open inbox</a>
                <button type="button" @click="close()" class="rounded-full px-2 py-1 text-lg leading-none text-text-muted hover:bg-surface-muted" aria-label="Close chat">×</button>
            </div>
        </header>

        <template x-if="canChat && !selected">
            <div class="min-h-0 flex-1 overflow-y-auto">
                <div class="border-b border-surface-border p-3">
                    <input type="search" x-model="searchTerm" placeholder="Search conversations" class="fs-input w-full py-2 text-sm">
                </div>
                <div class="divide-y divide-surface-border">
                    <template x-for="conversation in conversations" :key="conversation.id">
                        <button type="button" x-show="!searchTerm || (conversation.name + ' ' + conversation.preview + ' ' + (conversation.product || '')).toLowerCase().includes(searchTerm.toLowerCase())" @click="selectConversation(conversation)" class="flex w-full items-center gap-3 p-3 text-left transition-colors hover:bg-surface-muted">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent-subtle text-sm font-semibold text-accent" x-text="conversation.name.slice(0, 1).toUpperCase()"></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2"><span class="truncate text-sm font-semibold" x-text="conversation.name"></span><span x-show="conversation.unread" class="rounded-full bg-accent px-1.5 py-0.5 text-[10px] text-white" x-text="conversation.unread > 99 ? '99+' : conversation.unread"></span></span>
                                <span class="mt-1 block truncate text-xs text-text-muted" x-text="conversation.preview"></span>
                                <span x-show="conversation.product" class="mt-0.5 block truncate text-[11px] text-text-muted" x-text="'[Product] ' + conversation.product"></span>
                            </span>
                        </button>
                    </template>
                    <div x-show="conversations.length === 0" class="p-6 text-center">
                        <p class="text-sm font-medium">No conversations yet</p>
                        <p class="mt-1 text-xs text-text-muted">Start a chat from a product page.</p>
                        <a href="{{ route('catalog.index') }}" class="mt-3 inline-block text-sm text-accent underline">Browse products</a>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="canChat && selected">
            <div class="flex min-h-0 flex-1 flex-col">
                <p x-show="selected.product" class="truncate border-b border-surface-border px-4 py-2 text-xs text-text-muted" x-text="selected.product ? 'About ' + selected.product : ''"></p>
                <div x-ref="feed" class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-surface-muted/40 p-3" aria-live="polite">
                    <template x-for="message in messages" :key="message.id">
                        <div class="flex" :class="message.sender_id === userId ? 'justify-end' : 'justify-start'">
                            <div class="max-w-[85%] rounded-2xl px-3 py-2" :class="message.sender_id === userId ? 'bg-accent text-white' : 'bg-surface'">
                                <p class="whitespace-pre-wrap break-words text-sm" x-text="message.body"></p>
                                <p class="mt-1 text-[10px] opacity-70" x-text="message.created_at"></p>
                            </div>
                        </div>
                    </template>
                    <p x-show="messages.length === 0" class="py-8 text-center text-xs text-text-muted">No messages yet. Say hello!</p>
                </div>
                <form @submit.prevent="sendMessage()" class="flex items-end gap-2 border-t border-surface-border p-3">
                    <textarea x-model="draft" maxlength="4000" rows="2" class="fs-input min-w-0 flex-1 resize-none text-sm" placeholder="Write a message…" @keydown.ctrl.enter.prevent="sendMessage()"></textarea>
                    <button type="submit" :disabled="!draft.trim() || sending" class="btn-accent shrink-0 px-3 py-2">Send</button>
                </form>
            </div>
        </template>

        <div x-show="!canChat" class="p-6 text-center">
            @if(!$dockUser)
                <p class="text-sm font-medium">Sign in to use FarSell Chat</p>
                <p class="mt-1 text-xs text-text-muted">Message shops about their products.</p>
                <a href="{{ route('login') }}" class="btn-accent mt-4 inline-flex">Sign in</a>
            @else
                <p class="text-sm font-medium">Chat is available to buyers and sellers</p>
                <p class="mt-1 text-xs text-text-muted">Switch to a buyer or seller account to message a shop.</p>
            @endif
        </div>
    </section>

    <button type="button" @click="toggle()" class="relative flex h-12 items-center gap-2 rounded-full bg-accent px-5 text-sm font-semibold text-white shadow-lg transition hover:opacity-90" aria-label="Open FarSell Chat" :aria-expanded="open.toString()">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8A8.5 8.5 0 0 1 8.7 3.9a8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z"/></svg>
        <span>Chat</span>
        <span x-show="unreadTotal > 0" class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white" x-text="unreadTotal > 99 ? '99+' : unreadTotal"></span>
    </button>
</div>
