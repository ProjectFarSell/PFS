@extends('layouts.app')
@section('title', 'Seller application · FarSell')
@section('content')
    <div class="mx-auto max-w-2xl fs-card p-5 sm:p-7">
        <a href="{{ route('account.profile') }}" class="text-sm text-accent underline">Back to My Profile</a>
        <h1 class="mt-4 text-2xl font-semibold">Open your FarSell shop</h1>
        <p class="mt-2 text-sm text-text-muted">Tell us about your shop. Admin approval creates your storefront and enables product listing. Applying does not automatically grant seller access.</p>
        @if($application)
            <p class="mt-4"><span class="badge badge-neutral">{{ ucfirst($application->status) }}</span></p>
            @if($application->review_note)
                <div class="mt-3 rounded-lg bg-surface-muted p-3 text-sm"><p class="font-semibold">Review note</p><p class="mt-1 whitespace-pre-line break-words">{{ $application->review_note }}</p></div>
            @endif
        @endif
        @if($errors->any())
            <ul role="alert" class="mt-4 list-disc pl-5 text-sm text-error">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif
        @if($application?->status === 'approved')
            <p class="mt-4 text-sm">Your application is approved. Contact an administrator if your shop is unavailable.</p>
        @else
            <form method="post" action="{{ route('seller.apply.store') }}" class="mt-5 space-y-4">
                @csrf
                <label class="block text-sm">Shop name<input name="shop_name" required maxlength="120" value="{{ old('shop_name', $application?->shop_name) }}" class="mt-1 w-full rounded-lg"></label>
                <label class="block text-sm">Shop city<input name="city" required maxlength="80" value="{{ old('city', $application?->city) }}" class="mt-1 w-full rounded-lg"></label>
                <label class="block text-sm">Tagline (optional)<input name="tagline" maxlength="180" value="{{ old('tagline', $application?->tagline) }}" class="mt-1 w-full rounded-lg"></label>
                <label class="block text-sm">What will you sell?<textarea name="description" required maxlength="2000" rows="4" class="mt-1 w-full rounded-lg">{{ old('description', $application?->description) }}</textarea></label>
                <button class="btn-accent">{{ $application ? 'Update and resubmit' : 'Submit seller application' }}</button>
            </form>
        @endif
    </div>
@endsection
