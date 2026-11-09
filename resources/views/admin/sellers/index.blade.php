@extends('layouts.portal')
@section('title', 'Seller applications · FarSell')
@section('content')
    <x-ui.button variant="link" :href="route('admin.dashboard')" class="text-sm text-accent underline">Back to Admin Dashboard</x-ui.button>
    <section class="fs-card mt-4 p-5">
        <h1 class="text-xl font-semibold">Seller applications</h1>
        <p class="mt-2 text-sm text-text-muted">Oldest submissions first. Approval creates a shop and grants seller access.</p>
        <form method="get" action="{{ route('admin.sellers.index') }}" class="my-4 flex items-center gap-3">
            <label for="seller-status" class="text-sm">Status</label>
            <select name="status" id="seller-status" class="rounded-lg text-sm">@foreach(['pending', 'approved', 'rejected'] as $status)<option value="{{ $status }}" @selected($selectedStatus === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
            <x-ui.button variant="primary" size="lg" type="submit">Filter</x-ui.button>
        </form>
        <div class="divide-y divide-surface-border">
            @forelse($applications as $application)
                <article class="relative flex flex-wrap items-center justify-between gap-3 py-4">
                    <div class="min-w-0 break-words"><h2 class="font-semibold">{{ $application->shop_name }}</h2><p class="mt-1 text-sm text-text-muted">{{ $application->user->name }} · {{ $application->city }}</p></div>
                    <x-ui.button variant="link" :href="route('admin.sellers.show', $application)" class="ml-auto after:absolute after:inset-0 after:content-['']">Review application #{{ $application->id }}</x-ui.button>
                </article>
            @empty
                <p class="py-6 text-sm text-text-muted">No {{ $selectedStatus }} seller applications.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $applications->links() }}</div>
    </section>
@endsection
