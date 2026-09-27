@extends(auth()->user()->role === \App\Enums\UserRole::Rider ? 'layouts.portal' : 'layouts.app')

@section('title', 'Rider profile · FarSell')

@section('content')
    <a href="{{ route('rider.dashboard') }}" class="inline-block mb-3 text-sm text-accent underline">← Rider Dashboard</a>
    <div class="max-w-lg mx-auto rounded-2xl bg-surface border border-surface-border p-5">
        <p class="text-xs uppercase tracking-wide text-accent font-semibold">Courier card</p>
        <h1 class="text-xl font-semibold mt-1">{{ auth()->user()->name }}</h1>
        <p class="text-sm text-text-muted">{{ $profile->city }} · {{ $profile->vehicle_type }}</p>
        <p class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-medium
            {{ $profile->status->value === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">
            {{ ucfirst($profile->status->value) }}
        </p>
        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-text-muted">Plate</dt>
                <dd>{{ $profile->plate_number ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">License</dt>
                <dd>{{ $profile->license_no }}</dd>
            </div>
        </dl>
        @if ($profile->bio)
            <p class="text-sm text-text-muted mt-4">{{ $profile->bio }}</p>
        @endif

        @if ($profile->documents->isNotEmpty())
            <div class="mt-4">
                <p class="text-xs uppercase tracking-wide text-text-muted mb-2">Submitted documents</p>
                <ul class="space-y-1">
                    @foreach ($profile->documents as $document)
                        <li class="flex items-center justify-between text-sm rounded-lg bg-surface-muted px-3 py-2">
                            <span class="capitalize">{{ str_replace('_', ' ', $document->document_type) }}</span>
                            <span class="text-xs {{ $document->verified ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ $document->verified ? 'Verified' : 'Pending review' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($profile->review_note)
            <div class="mt-4 rounded-lg bg-surface-muted p-3 text-sm">
                <p class="font-semibold">Administrator's review note</p>
                <p class="mt-1 whitespace-pre-line break-words">{{ $profile->review_note }}</p>
            </div>
        @endif
        @if(in_array($profile->status, [\App\Enums\RiderStatus::Pending, \App\Enums\RiderStatus::Rejected], true))
            <a href="{{ route('rider.register') }}" class="inline-block mt-4 text-sm text-accent hover:text-accent font-medium">Update application</a>
        @else
            <p class="mt-4 text-sm text-text-muted">Contact an administrator if your rider details need updating.</p>
        @endif
    </div>
@endsection
