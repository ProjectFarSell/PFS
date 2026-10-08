@extends('layouts.portal')
@section('title', 'Chat · FarSell')
@section('content')
<div class="mx-auto max-w-6xl" x-data="chatThread({{ (int) $conversation->id }}, {{ (int) auth()->id() }}, @js(route('chat.messages', $conversation)), {{ (int) ($messages->last()?->id ?? 0) }})" x-init="start()" x-on:beforeunload.window="stop()">
    <section class="fs-card overflow-hidden">
        <header class="flex h-14 items-center justify-between border-b border-surface-border px-5">
            <h1 class="text-lg font-semibold text-accent">Chat <span class="ml-1 text-sm font-normal text-text-muted">({{ $unreadTotal }})</span></h1>
            <span class="text-xs text-text-muted">Buyer and seller messages</span>
        </header>
        <div class="grid min-h-[68vh] md:grid-cols-[300px_minmax(0,1fr)]">
            @include('chat.partials.sidebar', ['activeConversation' => $conversation])
            <main class="flex min-h-[68vh] flex-col border-l border-surface-border">
                <header class="flex min-h-[68px] items-center gap-3 border-b border-surface-border px-4 py-3">
                    <a href="{{ route('chat.index') }}" class="mr-1 text-sm text-accent md:hidden">←</a>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent-subtle text-sm font-semibold text-accent">
                        {{ mb_strtoupper(mb_substr(auth()->id() === $conversation->buyer_id ? $conversation->shop->name : $conversation->buyer->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold">{{ auth()->id() === $conversation->buyer_id ? $conversation->shop->name : $conversation->buyer->name }}</h2>
                        @if($conversation->product)<p class="truncate text-xs text-text-muted">About {{ $conversation->product->name }}</p>@endif
                    </div>
                </header>
                <div x-ref="feed" class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-surface-muted/40 p-4" aria-live="polite">
                    @foreach($messages as $message)
                        <div class="flex {{ $message->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[85%] rounded-2xl px-4 py-2 {{ $message->sender_id === auth()->id() ? 'bg-accent text-white' : 'bg-surface' }}">
                                <p class="whitespace-pre-wrap break-words text-sm">{{ $message->body }}</p>
                                <p class="mt-1 text-[11px] opacity-70">{{ $message->created_at->format('M j, g:i a') }}</p>
                            </div>
                        </div>
                    @endforeach
                    <template x-for="message in incoming" :key="message.id">
                        <div class="flex" :class="message.sender_id === userId ? 'justify-end' : 'justify-start'">
                            <div class="max-w-[85%] rounded-2xl px-4 py-2" :class="message.sender_id === userId ? 'bg-accent text-white' : 'bg-surface'">
                                <p class="whitespace-pre-wrap break-words text-sm" x-text="message.body"></p>
                                <p class="mt-1 text-[11px] opacity-70" x-text="message.created_at"></p>
                            </div>
                        </div>
                    </template>
                </div>
                <form method="post" action="{{ route('chat.send', $conversation) }}" class="flex items-end gap-2 border-t border-surface-border p-3">
                    @csrf
                    <textarea name="body" rows="2" maxlength="4000" required class="fs-input min-w-0 flex-1 resize-y" placeholder="Write a message…">{{ old('body') }}</textarea>
                    <button class="btn-accent shrink-0">Send</button>
                </form>
                @error('body')<p class="px-4 pb-3 text-sm text-error">{{ $message }}</p>@enderror
            </main>
        </div>
    </section>
</div>
@endsection
