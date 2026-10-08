@extends('layouts.portal')
@section('title', 'Chat · FarSell')
@section('content')
<div class="mx-auto max-w-6xl">
    <section class="fs-card overflow-hidden">
        <header class="flex h-14 items-center justify-between border-b border-surface-border px-5">
            <h1 class="text-lg font-semibold text-accent">Chat <span class="ml-1 text-sm font-normal text-text-muted">({{ $unreadTotal }})</span></h1>
            <span class="text-xs text-text-muted">Buyer and seller messages</span>
        </header>
        <div class="grid min-h-[68vh] md:grid-cols-[300px_minmax(0,1fr)]">
            @include('chat.partials.sidebar', ['activeConversation' => null])
            <main class="hidden items-center justify-center border-l border-surface-border bg-surface-muted p-8 text-center md:flex">
                <div class="max-w-sm">
                    <div class="mx-auto flex h-32 w-44 items-center justify-center" aria-hidden="true">
                        <svg viewBox="0 0 176 128" class="h-full w-full" fill="none">
                            <path d="M25 101h126M44 97l8-58a7 7 0 0 1 7-6h62a7 7 0 0 1 7 7l6 57" stroke="#9CA3AF" stroke-width="7" stroke-linecap="round"/>
                            <path d="M55 50h55a5 5 0 0 1 5 5v24H55a5 5 0 0 1-5-5V55a5 5 0 0 1 5-5Z" fill="#3478F6"/>
                            <path d="M62 59h34M62 68h22" stroke="white" stroke-width="3" stroke-linecap="round"/>
                            <path d="M112 68h31a5 5 0 0 1 5 5v16a5 5 0 0 1-5 5h-20l-8 6v-6h-3a5 5 0 0 1-5-5V73a5 5 0 0 1 5-5Z" fill="#F45132"/>
                            <circle cx="122" cy="81" r="2" fill="white"/><circle cx="132" cy="81" r="2" fill="white"/><circle cx="142" cy="81" r="2" fill="white"/>
                        </svg>
                    </div>
                    <h2 class="mt-5 text-lg font-semibold">Welcome to FarSell Chat</h2>
                    <p class="mt-2 text-sm text-text-muted">Choose a conversation to continue chatting.</p>
                </div>
            </main>
            <div class="flex items-center justify-center p-8 text-center md:hidden">
                <p class="text-sm text-text-muted">Choose a conversation above, or start a chat from a product page.</p>
            </div>
        </div>
    </section>
</div>
@endsection
