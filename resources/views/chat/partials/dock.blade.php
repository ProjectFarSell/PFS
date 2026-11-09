@php
    $dockUser = auth()->user();
    $canUseChatDock = $dockUser && in_array($dockUser->role, [\App\Enums\UserRole::Buyer, \App\Enums\UserRole::Seller], true);
@endphp
<div class="fixed {{ ($hasBuyerActionBar ?? false) ? 'bottom-[calc(9rem+env(safe-area-inset-bottom))] md:bottom-4' : (($canUseCart ?? false) ? 'bottom-20 md:bottom-4' : 'bottom-4') }} right-4 z-[45]" :inert="typeof drawerOpen !== 'undefined' && drawerOpen" x-data="chatDock(@js(route('chat.widget.conversations')), @js(url('/chat')), @js($canUseChatDock), {{ (int) ($chatUnreadCount ?? 0) }}, {{ (int) ($dockUser?->id ?? 0) }})" x-on:beforeunload.window="stopPolling()">
    <section x-show="open" x-cloak x-transition class="mb-3 flex h-[min(34rem,calc(100dvh-7rem))] w-[min(23rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-surface-border bg-surface shadow-2xl {{ ($hasBuyerActionBar ?? false) ? 'max-md:max-h-[calc(100dvh-13rem-env(safe-area-inset-bottom))]' : '' }}" aria-label="Chat panel">
        <header class="flex min-h-14 items-center justify-between gap-3 border-b border-surface-border px-4">
            <div class="flex min-w-0 items-center gap-2">
                <template x-if="selected"><x-ui.icon-button variant="tertiary" label="Back to conversations" icon="previous" type="button" @click="backToList()" class="shrink-0 rounded-full p-1 text-text-muted hover:bg-surface-muted"></x-ui.icon-button></template>
                <h2 class="truncate font-semibold text-accent" x-text="selected ? selected.name : 'Chat'"></h2>
                <span x-show="!selected && unreadTotal > 0" class="rounded-full bg-accent px-2 py-0.5 text-[10px] font-semibold text-white" x-text="unreadTotal > 99 ? '99+' : unreadTotal"></span>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <x-ui.button variant="link" size="sm" :href="route('chat.index')">Open inbox</x-ui.button>
                <x-ui.icon-button variant="tertiary" label="Close chat" icon="close" type="button" @click="close()" class="rounded-full px-2 py-1 text-lg leading-none text-text-muted hover:bg-surface-muted"></x-ui.icon-button>
            </div>
        </header>

        <template x-if="canChat && !selected">
            <div class="min-h-0 flex-1 overflow-y-auto">
                <div class="border-b border-surface-border p-3">
                    <input type="search" x-model="searchTerm" placeholder="Search conversations" class="fs-input w-full py-2 text-sm">
                </div>
                <div class="divide-y divide-surface-border">
                    <template x-for="conversation in conversations" :key="conversation.id">
                        <x-ui.button variant="tertiary" type="button" x-show="!searchTerm || (conversation.name + ' ' + conversation.preview + ' ' + (conversation.product || '')).toLowerCase().includes(searchTerm.toLowerCase())" @click="selectConversation(conversation)" class="flex w-full items-center gap-3 p-3 text-left transition-colors hover:bg-surface-muted">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent-subtle text-sm font-semibold text-accent" x-text="conversation.name.slice(0, 1).toUpperCase()"></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2"><span class="truncate text-sm font-semibold" x-text="conversation.name"></span><span x-show="conversation.unread" class="rounded-full bg-accent px-1.5 py-0.5 text-[10px] text-white" x-text="conversation.unread > 99 ? '99+' : conversation.unread"></span></span>
                                <span class="mt-1 block truncate text-xs text-text-muted" x-text="conversation.preview"></span>
                                <span x-show="conversation.product" class="mt-0.5 block truncate text-[11px] text-text-muted" x-text="'[Product] ' + conversation.product"></span>
                            </span>
                        </x-ui.button>
                    </template>
                    <div x-show="conversations.length === 0" class="p-6 text-center">
                        <p class="text-sm font-medium">No conversations yet</p>
                        <p class="mt-1 text-xs text-text-muted">Start a chat from a product page.</p>
                        <x-ui.button variant="link" :href="route('catalog.index')" class="mt-3 inline-block text-sm text-accent underline">Browse products</x-ui.button>
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
                    <x-ui.button variant="primary" size="lg" type="submit" loading-when="sending" ::disabled="!draft.trim() || sending" class="shrink-0">Send</x-ui.button>
                </form>
            </div>
        </template>

        <div x-show="!canChat" class="p-6 text-center">
            @if(!$dockUser)
                <p class="text-sm font-medium">Sign in to use FarSell Chat</p>
                <p class="mt-1 text-xs text-text-muted">Message shops about their products.</p>
                <x-ui.button variant="primary" size="lg" :href="route('login')" class="mt-4 inline-flex">Sign in</x-ui.button>
            @else
                <p class="text-sm font-medium">Chat is available to buyers and sellers</p>
                <p class="mt-1 text-xs text-text-muted">Switch to a buyer or seller account to message a shop.</p>
            @endif
        </div>
    </section>

    <x-ui.button variant="primary" type="button" @click="toggle()" class="relative shadow-lg" aria-label="Open FarSell Chat" ::aria-expanded="open.toString()">
        <x-ui.icon name="chat" />
        <span>Chat</span>
        <span x-show="unreadTotal > 0" class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white" x-text="unreadTotal > 99 ? '99+' : unreadTotal"></span>
    </x-ui.button>
</div>
