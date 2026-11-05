@extends('layouts.portal')
@section('title', (match ($section) { 'suspended' => 'Suspended accounts', 'restore' => 'Restore accounts', default => 'Active accounts' }).' - Account protection')
@section('content')
<div class="space-y-5">
    <h1 class="text-2xl font-bold">Account protection</h1>
    <nav class="flex flex-wrap gap-3" aria-label="Account protection pages">
        <a href="{{ route('admin.security.accounts') }}" class="{{ $section === 'active' ? 'btn-accent' : 'btn-outline' }}" @if($section === 'active') aria-current="page" @endif>Active accounts ({{ $activeCount }})</a>
        <a href="{{ route('admin.security.accounts.suspended') }}" class="{{ $section === 'suspended' ? 'btn-accent' : 'btn-outline' }}" @if($section === 'suspended') aria-current="page" @endif>Suspended accounts ({{ $suspendedCount }})</a>
        <a href="{{ route('admin.security.accounts.restore') }}" class="{{ $section === 'restore' ? 'btn-accent' : 'btn-outline' }}" @if($section === 'restore') aria-current="page" @endif>Restore accounts</a>
    </nav>
    <h2 class="text-xl font-semibold">{{ match ($section) { 'suspended' => 'Suspended accounts', 'restore' => 'Restore account access', default => 'Active accounts — manage suspensions' } }}</h2>
    <p class="text-sm text-text-muted">Suspension blocks sign-in, revokes sessions and hides a seller's shop. Existing orders, evidence and earnings remain. Arrange outstanding deliveries separately before suspending a rider or seller. Admin accounts cannot be suspended here.</p>
    @if($errors->any())<p role="alert" class="text-error">{{ $errors->first() }}</p>@endif
    <form method="get" action="{{ route($pageRoute) }}" class="flex flex-wrap gap-3"><label class="sr-only" for="account-search">Name or email</label><input id="account-search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Search name or email" class="min-w-0 rounded-lg border border-surface-border bg-surface p-3"><button class="btn-outline">Search</button></form>
    @forelse($users as $account)
        <article class="fs-card p-5 space-y-3"><h2 class="font-semibold">{{ $account->name }} · {{ ucfirst($account->role->value) }}</h2><p class="text-sm">{{ $account->email }}</p><p class="text-sm">{{ $account->suspended_at ? 'Suspended' : 'Active' }}</p>
        @if($account->suspension_reason)<p class="text-sm">Latest access decision: {{ $account->suspension_reason }}</p>@endif
        @if($section === 'suspended' && $account->suspended_at)<p class="text-xs text-text-muted">Suspended {{ $account->suspended_at->format('M j, Y g:i A') }}</p>@endif
        @if($section !== 'suspended' && $account->role !== \App\Enums\UserRole::Admin)
            <form method="post" action="{{ route('admin.security.accounts.update', $account) }}" class="space-y-3">@csrf<input type="hidden" name="action" value="{{ $account->suspended_at ? 'restore' : 'suspend' }}"><label class="block text-sm">Reason<textarea name="reason" required minlength="5" maxlength="1000" class="mt-1 w-full rounded-lg border border-surface-border bg-surface p-3"></textarea></label>
            @if($account->suspended_at && $account->role === \App\Enums\UserRole::Seller)<label class="block text-sm"><input type="checkbox" name="restore_shop" value="1"> Also approve making this seller's shop visible again</label>@endif
            <button class="btn-outline">{{ $account->suspended_at ? 'Restore account' : 'Suspend account' }}</button></form>
        @endif</article>
    @empty
        <p class="fs-card p-5 text-sm text-text-muted">{{ $suspended ? 'No suspended accounts found.' : 'No active accounts found.' }}</p>
    @endforelse
    {{ $users->links() }}
</div>
@endsection
