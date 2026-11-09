@extends('layouts.portal')
@section('title', 'Security audit trail - FarSell')
@section('content')
<div class="space-y-5"><h1 class="text-2xl font-bold">Security audit trail</h1><p class="text-sm text-text-muted">Selected account, catalog, stock, application and refund changes. Audit records cannot be edited or deleted through the website. Actor IDs refer to the account that performed the action.</p>
<form method="get" class="flex flex-wrap gap-3"><label class="text-sm">Action<input name="action" value="{{ request('action') }}" maxlength="80" class="mt-1 block rounded-lg border border-surface-border bg-surface p-2"></label><label class="text-sm">Actor ID<input type="number" name="actor_id" min="1" value="{{ request('actor_id') }}" class="mt-1 block rounded-lg border border-surface-border bg-surface p-2"></label><x-ui.button variant="secondary" type="submit">Filter</x-ui.button></form>
@forelse($events as $event)<article class="fs-card p-4 space-y-2"><h2 class="font-semibold">{{ $event->action }}</h2><p class="text-xs text-text-muted">{{ $event->created_at->format('Y-m-d H:i:s') }} · Actor {{ $event->actor_id ?? 'Deleted account' }} · {{ $event->target_type }} #{{ $event->target_id }}</p>@foreach($event->changes as $field => $change)<p class="break-all text-sm"><strong>{{ $field }}</strong>: {{ json_encode($change['before'], JSON_UNESCAPED_UNICODE) }} → {{ json_encode($change['after'], JSON_UNESCAPED_UNICODE) }}</p>@endforeach</article>@empty<p>No audit events match these filters.</p>@endforelse
{{ $events->links() }}</div>
@endsection
