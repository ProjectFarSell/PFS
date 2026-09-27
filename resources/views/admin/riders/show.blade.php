@extends('layouts.portal')

@section('title', 'Review rider application · FarSell')

@section('content')
    <a href="{{ route('admin.riders.index') }}" class="text-sm text-accent underline">Back to rider applications</a>
    <section class="fs-card mx-auto mt-4 max-w-2xl p-5">
        <h1 class="text-xl font-semibold">{{ $profile->user->name }}</h1>
        <p class="mt-1 text-sm text-text-muted break-words">{{ $profile->user->email }}</p>
        <span class="badge badge-neutral mt-3">{{ ucfirst($profile->status->value) }}</span>
        <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            @foreach(['Account role' => $profile->user->role->value, 'Vehicle' => $profile->vehicle_type, 'City' => $profile->city, 'Plate number' => $profile->plate_number, 'License / ID number' => $profile->license_no] as $label => $value)
                <div><dt class="text-text-muted">{{ $label }}</dt><dd class="mt-1 break-words">{{ $value ?: 'Not supplied' }}</dd></div>
            @endforeach
        </dl>
        @if($profile->bio)<p class="mt-4 text-sm whitespace-pre-line break-words">{{ $profile->bio }}</p>@endif

        <h2 class="mt-6 font-semibold">Submitted documents</h2>
        <p class="mt-1 text-xs text-text-muted">Private downloads, available only to admins. Uploads are currently optional. Approval does not automatically mark documents as verified.</p>
        <ul class="mt-3 space-y-3">
            @forelse($profile->documents as $document)
                <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span>{{ ucfirst(str_replace('_', ' ', $document->document_type)) }} · {{ $document->created_at->format('M j, Y g:i A') }} · {{ $document->verified ? 'Verified' : 'Not verified' }}</span>
                    <a href="{{ route('admin.riders.document', [$profile, $document]) }}" class="text-accent underline">Download document #{{ $document->id }}</a>
                </li>
            @empty
                <li class="text-sm text-text-muted">No documents uploaded. Verify the applicant's details before approving.</li>
            @endforelse
        </ul>

        @if($errors->any())
            <ul role="alert" class="mt-4 list-disc pl-5 text-sm text-red-600">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif

        @if($profile->status === \App\Enums\RiderStatus::Pending)
            @php($canApprove = in_array($profile->user->role, [\App\Enums\UserRole::Buyer, \App\Enums\UserRole::Rider], true))
            @if(!$canApprove)
                <p class="mt-4 text-sm text-text-muted">Seller and admin accounts cannot be converted here. Ask the applicant to use a separate rider account.</p>
            @endif
            <form action="{{ route('admin.riders.review', $profile) }}" method="post" class="mt-6 space-y-4 border-t border-surface-border pt-4">
                @csrf
                <input type="hidden" name="review_token" value="{{ $reviewToken }}">
                <label class="block text-sm">Review note (required for rejection; visible to applicant)
                    <textarea name="review_note" maxlength="1000" rows="3" class="mt-2 w-full rounded-lg">{{ old('review_note') }}</textarea>
                </label>
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="confirmed" value="1" required class="mt-1 rounded">
                    <span>I have reviewed these details and any submitted documents. Approving grants rider access immediately.</span>
                </label>
                <div class="flex flex-wrap gap-3">
                    @if($canApprove)<button name="decision" value="approve" class="btn-accent">Approve rider</button>@endif
                    <button name="decision" value="reject" class="rounded-lg border border-surface-border px-4 py-2 text-sm font-semibold">Reject application</button>
                </div>
            </form>
        @else
            <div class="mt-6 border-t border-surface-border pt-4 text-sm">
                <p>This application is not pending. No review actions are available.</p>
                @if($profile->reviewed_at)
                    <p class="mt-2 text-text-muted">Last reviewed {{ $profile->reviewed_at->format('M j, Y g:i A') }} by {{ $profile->reviewer?->name ?? 'Unavailable administrator' }}.</p>
                @endif
                @if($profile->review_note)<p class="mt-2 whitespace-pre-line break-words">{{ $profile->review_note }}</p>@endif
            </div>
        @endif
    </section>
@endsection
