@extends('layouts.app')
@section('title', 'Seller applications · FarSell')
@section('content')
    <a href="{{ route('admin.dashboard') }}" class="text-sm text-accent underline">Back to Admin Dashboard</a>
    <section class="fs-card mt-4 p-5">
        <h1 class="text-xl font-semibold">Seller applications</h1>
        <p class="mt-2 text-sm text-text-muted">Oldest submissions first. Approval creates a shop and grants seller access.</p>
        <form method="get" action="{{ route('admin.sellers.index') }}" class="my-4 flex items-center gap-3">
            <label for="seller-status" class="text-sm">Status</label>
            <select name="status" id="seller-status" class="rounded-lg text-sm">@foreach(['pending', 'approved', 'rejected'] as $status)<option value="{{ $status }}" @selected($selectedStatus === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
            <button class="btn-accent">Filter</button>
        </form>
        <div class="divide-y divide-surface-border">
            @forelse($applications as $application)
                <article class="flex flex-wrap justify-between gap-3 py-4">
                    <div class="min-w-0 break-words"><h2 class="font-semibold">{{ $application->shop_name }}</h2><p class="mt-1 text-sm text-text-muted">{{ $application->user->name }} · {{ $application->city }}</p></div>
                    <a href="{{ route('admin.sellers.show', $application) }}" class="text-sm text-accent underline">Review application #{{ $application->id }}</a>
                </article>
            @empty
                <p class="py-6 text-sm text-text-muted">No {{ $selectedStatus }} seller applications.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $applications->links() }}</div>
    </section>
@endsection
