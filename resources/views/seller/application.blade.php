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
            <form method="post" action="{{ route('seller.apply.store') }}" class="mt-5 space-y-4" data-address-location-form data-location-field-names="psgc_region_id,psgc_province_id,psgc_city_municipality_id">
                @csrf
                <label class="block text-sm">Shop name<input name="shop_name" required maxlength="120" value="{{ old('shop_name', $application?->shop_name) }}" class="mt-1 w-full rounded-lg"></label>
                <label class="block text-sm">Shop region
                    <select name="psgc_region_id" required class="mt-1 w-full rounded-lg border-surface-border text-sm focus:border-accent focus:ring-accent">
                        <option value="">Select region</option>
                        @foreach($regions as $region)
                            <option value="{{ $region->id }}" @selected((string)$selectedLocation['region_id'] === (string)$region->id)>{{ $region->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">Shop province
                    <select name="psgc_province_id" required disabled data-selected="{{ $selectedLocation['province_id'] }}" data-url="{{ route('seller.apply.locations.provinces', [], false) }}" data-parent-param="region_id" class="mt-1 w-full rounded-lg border-surface-border text-sm disabled:bg-surface-muted focus:border-accent focus:ring-accent">
                        <option value="">Select province</option>
                    </select>
                </label>
                <label class="block text-sm">Shop city / municipality
                    <select name="psgc_city_municipality_id" required disabled data-selected="{{ $selectedLocation['city_municipality_id'] }}" data-url="{{ route('seller.apply.locations.cities-municipalities', [], false) }}" data-parent-param="province_id" class="mt-1 w-full rounded-lg border-surface-border text-sm disabled:bg-surface-muted focus:border-accent focus:ring-accent">
                        <option value="">Select city or municipality</option>
                    </select>
                </label>
                <p data-location-status role="status" aria-live="polite" class="text-sm text-text-muted"></p>
                <button type="button" data-location-retry hidden class="text-sm text-accent underline">Retry loading locations</button>
                <noscript><p class="text-sm text-error">Enable JavaScript to load the official location options.</p></noscript>
                <label class="block text-sm">Tagline (optional)<input name="tagline" maxlength="180" value="{{ old('tagline', $application?->tagline) }}" class="mt-1 w-full rounded-lg"></label>
                <fieldset class="rounded-xl border border-surface-border p-4">
                    <legend class="px-1 text-sm font-medium">Categories you plan to sell</legend>
                    @if($categories->isEmpty())
                        <p class="text-sm text-error">No categories are available yet. Ask an administrator to create one.</p>
                    @else
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach($categories as $category)
                                <label class="flex items-center gap-2 rounded-lg border border-surface-border p-3 text-sm">
                                    <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, array_map('intval', $selectedCategories), true)) class="rounded border-surface-border text-accent focus:ring-accent">
                                    <span>{{ $category->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </fieldset>
                <label class="block text-sm">Tell us what you plan to sell<textarea name="description" required maxlength="2000" rows="4" class="mt-1 w-full rounded-lg">{{ old('description', $application?->description) }}</textarea></label>
                <button class="btn-accent">{{ $application ? 'Update and resubmit' : 'Submit seller application' }}</button>
            </form>
        @endif
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/address-location.js') }}" defer></script>
@endpush
