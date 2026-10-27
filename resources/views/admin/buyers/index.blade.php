@extends('layouts.portal')

@section('title', 'Buyer accounts · FarSell')

@section('content')
    <a href="{{ route('admin.dashboard') }}" class="text-sm text-accent underline">Back to Admin Dashboard</a>
    <section class="fs-card mt-4 p-5">
        <h1 class="text-xl font-semibold">Buyer accounts</h1>
        <p class="mt-2 text-sm text-text-muted">Marketplace buyer accounts and their order counts.</p>
        <div class="mt-4 divide-y divide-surface-border">
            @forelse($buyers as $buyer)
                <article class="flex flex-wrap items-center justify-between gap-3 py-4">
                    <div class="min-w-0"><h2 class="break-words font-semibold">{{ $buyer->name }}</h2><p class="break-words text-sm text-text-muted">{{ $buyer->email }}</p></div>
                    <div class="text-sm text-text-muted">{{ $buyer->orders_count }} {{ \Illuminate\Support\Str::plural('order', $buyer->orders_count) }}</div>
                </article>
            @empty
                <p class="py-6 text-sm text-text-muted">No buyer accounts found.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $buyers->links() }}</div>
    </section>
@endsection
