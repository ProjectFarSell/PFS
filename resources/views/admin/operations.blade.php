@extends('layouts.portal')
@section('title', 'Operations · FarSell')
@section('content')
<a href="{{ route('admin.dashboard') }}" class="text-accent underline">Back to admin dashboard</a>
<h1 class="mt-5 text-2xl font-bold">Operations &amp; release checks</h1>
<p class="mt-2 text-sm text-text-muted">Revision: {{ $revision }}. This page checks technical conditions; launch approval remains with the team.</p>
<section class="fs-card mt-5 p-5"><h2 class="font-semibold">Runtime health</h2><dl class="mt-4 grid gap-3 sm:grid-cols-2">
@foreach($status as $name => $value)<div><dt class="text-sm text-text-muted">{{ ucfirst(str_replace('_', ' ', $name)) }}</dt><dd class="font-semibold">{{ is_bool($value) ? ($value ? 'OK' : 'Needs attention') : ($value ?? 'Unavailable') }}</dd></div>@endforeach
</dl><p class="mt-4 text-xs text-text-muted">Scheduler and worker heartbeats are required on production. Local development can show them as unavailable until its background processes are running.</p></section>
<section class="fs-card mt-5 overflow-x-auto p-5"><h2 class="font-semibold">Technical readiness</h2><table class="mt-4 w-full text-left text-sm"><thead><tr><th class="p-2">Check</th><th class="p-2">Result</th><th class="p-2">Next action</th></tr></thead><tbody>
@foreach($checks as $check)<tr class="border-t border-surface-border"><td class="p-2">{{ ucfirst(str_replace('_', ' ', $check['name'])) }}</td><td class="p-2">{{ $check['pass'] ? 'PASS' : 'FAIL' }}</td><td class="p-2 text-text-muted">{{ $check['pass'] ? 'Checked' : $check['action'] }}</td></tr>@endforeach
</tbody></table></section>
@endsection
