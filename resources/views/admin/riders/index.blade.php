@extends('layouts.app')

@section('title', 'Rider applications · FarSell')

@section('content')
    <a href="{{ route('admin.dashboard') }}" class="text-sm text-accent underline">Back to Admin Dashboard</a>
    <section class="fs-card mt-4 p-5">
        <h1 class="text-xl font-semibold">Rider applications</h1>
        <p class="mt-2 text-sm text-text-muted">Oldest applications first. Approval enables rider access; rejection allows the applicant to resubmit.</p>
        <form method="get" action="{{ route('admin.riders.index') }}" class="my-4 flex flex-wrap items-center gap-3">
            <label for="application-status" class="text-sm">Status</label>
            <select id="application-status" name="status" class="rounded-lg text-sm">
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ ucfirst($status->value) }}</option>
                @endforeach
            </select>
            <button class="btn-accent">Filter</button>
        </form>
        <div class="divide-y divide-surface-border">
            @forelse($applications as $application)
                <article class="flex flex-wrap items-center justify-between gap-3 py-4">
                    <div class="min-w-0 break-words">
                        <h2 class="font-semibold">{{ $application->user->name }}</h2>
                        <p class="text-sm text-text-muted">{{ $application->user->email }}</p>
                        <p class="mt-1 text-sm">{{ ucfirst($application->vehicle_type) }} · {{ $application->city }}</p>
                        <p class="mt-1 text-xs text-text-muted">{{ ucfirst($application->status->value) }} · Submitted {{ $application->created_at->format('M j, Y') }}</p>
                    </div>
                    <a href="{{ route('admin.riders.show', $application) }}" class="text-sm text-accent underline">Review application #{{ $application->id }}</a>
                </article>
            @empty
                <p class="py-6 text-sm text-text-muted">No {{ $selectedStatus }} rider applications.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $applications->links() }}</div>
    </section>
@endsection
