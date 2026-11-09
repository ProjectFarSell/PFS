@extends('layouts.portal')
@section('title', 'Account deletion requests · FarSell')
@section('content')
<x-ui.button variant="link" :href="route('admin.dashboard')" class="text-accent underline">Back to admin dashboard</x-ui.button>
<h1 class="mt-5 text-2xl font-bold">Account deletion requests</h1>
<p class="mt-2 text-sm text-text-muted">Review each request and explain the next action to the account holder. Saving a response does not delete accounts, orders, or delivery records.</p>
<div class="mt-5 space-y-4">
@forelse($requests as $deletion)
    <section class="fs-card p-5">
        <div class="flex flex-wrap justify-between gap-2"><h2 class="font-semibold">{{ $deletion->user->name }} · {{ $deletion->user->email }}</h2><span class="text-sm">{{ ucfirst($deletion->status) }}</span></div>
        <p class="mt-2 text-sm text-text-muted">Role: {{ $deletion->user->role->value }} · Requested: {{ $deletion->created_at->format('Y-m-d H:i') }}</p>
        @if($deletion->status === 'pending')
            <form method="post" action="{{ route('admin.privacy-requests.review', $deletion) }}" class="mt-4 space-y-3">@csrf
                <label for="response-{{ $deletion->id }}" class="block text-sm">Response visible to the account holder</label>
                <textarea id="response-{{ $deletion->id }}" name="response" required maxlength="1000" class="fs-input" rows="3"></textarea>
                @error('response')<p role="alert" class="text-sm text-error">{{ $message }}</p>@enderror
                <x-ui.button variant="primary" size="lg" type="submit">Save review response</x-ui.button>
            </form>
        @else
            <p class="mt-3 whitespace-pre-wrap text-sm">{{ $deletion->response }}</p>
            <p class="mt-2 text-xs text-text-muted">Reviewed {{ $deletion->reviewed_at?->format('Y-m-d H:i') }} by {{ $deletion->reviewer?->name ?? 'Administrator' }}.</p>
        @endif
    </section>
@empty
    <p class="fs-card p-5">No account deletion requests yet.</p>
@endforelse
</div>
<div class="mt-5">{{ $requests->links() }}</div>
@endsection
