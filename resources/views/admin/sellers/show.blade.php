@extends('layouts.app')
@section('title', 'Review seller application · FarSell')
@section('content')
    <a href="{{ route('admin.sellers.index') }}" class="text-sm text-accent underline">Back to seller applications</a>
    <section class="fs-card mx-auto mt-4 max-w-2xl p-5">
        <h1 class="text-xl font-semibold">{{ $application->shop_name }}</h1>
        <span class="badge badge-neutral mt-3">{{ ucfirst($application->status) }}</span>
        <dl class="mt-4 space-y-3 text-sm">
            @foreach(['Applicant' => $application->user->name, 'Email' => $application->user->email, 'Current role' => $application->user->role->value, 'City' => $application->city, 'Tagline' => $application->tagline, 'What they will sell' => $application->description] as $label => $value)
                <div><dt class="text-text-muted">{{ $label }}</dt><dd class="mt-1 whitespace-pre-line break-words">{{ $value ?: 'Not supplied' }}</dd></div>
            @endforeach
        </dl>
        @if($errors->any())<ul role="alert" class="mt-4 list-disc pl-5 text-sm text-error">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        @if($application->status === 'pending')
            <form method="post" action="{{ route('admin.sellers.review', $application) }}" class="mt-6 space-y-4 border-t border-surface-border pt-4">
                @csrf
                <input type="hidden" name="revision" value="{{ $application->revision }}">
                <label class="block text-sm">Review note (required for rejection; visible to applicant)<textarea name="review_note" maxlength="1000" rows="3" class="mt-1 w-full rounded-lg">{{ old('review_note') }}</textarea></label>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirmed" value="1" required class="mt-1 rounded"><span>I reviewed this application. Approval creates an active shop and grants product-listing access immediately.</span></label>
                <div class="flex flex-wrap gap-3"><button name="decision" value="approve" class="btn-accent">Approve seller</button><button name="decision" value="reject" class="btn-outline">Reject application</button></div>
            </form>
        @else
            <p class="mt-5 text-sm text-text-muted">Reviewed {{ $application->reviewed_at?->format('M j, Y g:i A') }} by {{ $application->reviewer?->name ?? 'Unavailable administrator' }}.</p>
            @if($application->review_note)<p class="mt-2 text-sm whitespace-pre-line break-words">{{ $application->review_note }}</p>@endif
        @endif
    </section>
@endsection
