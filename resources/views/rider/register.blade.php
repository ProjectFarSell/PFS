@extends('layouts.app')

@section('title', 'Rider application · FarSell')

@section('content')
    <div class="max-w-lg mx-auto rounded-2xl bg-white border border-stone-200 p-5">
        <h1 class="text-lg font-semibold">Rider registration</h1>
        <p class="text-sm text-stone-500 mt-1">Doorzo-style courier profile. Approval is required before deliveries.</p>
        @if ($profile)
            <p class="mt-3 text-sm">Current status: <span class="font-medium capitalize">{{ $profile->status->value }}</span></p>
        @endif
        <form method="post" action="{{ route('rider.register') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
            @csrf
            <label class="block text-sm">Vehicle
                <select name="vehicle_type" class="mt-1 w-full rounded-lg border-stone-200 text-sm">
                    <option value="motorcycle">Motorcycle</option>
                    <option value="bicycle">Bicycle</option>
                    <option value="car">Car</option>
                    <option value="van">Van</option>
                </select>
            </label>
            <input name="plate_number" placeholder="Plate number" class="w-full rounded-lg border-stone-200 text-sm" value="{{ old('plate_number', $profile->plate_number ?? '') }}">
            <input name="license_no" required placeholder="License / ID number" class="w-full rounded-lg border-stone-200 text-sm" value="{{ old('license_no', $profile->license_no ?? '') }}">
            <input name="city" required placeholder="City" class="w-full rounded-lg border-stone-200 text-sm" value="{{ old('city', $profile->city ?? '') }}">
            <textarea name="bio" rows="3" placeholder="Short bio" class="w-full rounded-lg border-stone-200 text-sm">{{ old('bio', $profile->bio ?? '') }}</textarea>

            <label class="block text-sm">Driver's license (photo/scan)
                <input type="file" name="license_document" accept="image/*,.pdf" class="mt-1 w-full text-sm">
            </label>
            <label class="block text-sm">Valid ID
                <input type="file" name="id_document" accept="image/*,.pdf" class="mt-1 w-full text-sm">
            </label>
            <label class="block text-sm">Vehicle registration (OR/CR, if applicable)
                <input type="file" name="vehicle_reg_document" accept="image/*,.pdf" class="mt-1 w-full text-sm">
            </label>
            <p class="text-xs text-stone-500">Accepted: JPG, PNG, PDF. Max 5MB each.</p>

            @if ($errors->any())
                <ul class="text-sm text-red-600 list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <button class="w-full rounded-full bg-orange-500 text-white text-sm py-2.5">Submit application</button>
        </form>
    </div>
@endsection
