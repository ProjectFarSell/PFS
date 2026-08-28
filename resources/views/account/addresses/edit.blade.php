@extends('layouts.app')

@section('title', 'Edit address · FarSell')

@section('content')
    <div class="max-w-lg mx-auto rounded-2xl bg-white border border-stone-200 p-5">
        <h1 class="text-lg font-semibold mb-3">Edit address</h1>
        <form method="post" action="{{ route('account.addresses.update', $address) }}" class="space-y-3">
            @csrf
            @method('put')
            <input name="label" placeholder="Label (e.g. Home, Work)" value="{{ old('label', $address->label) }}" class="w-full rounded-lg border-stone-200 text-sm">
            <input name="line1" required placeholder="Street address" value="{{ old('line1', $address->line1) }}" class="w-full rounded-lg border-stone-200 text-sm">
            <input name="city" required placeholder="City" value="{{ old('city', $address->city) }}" class="w-full rounded-lg border-stone-200 text-sm">
            <input name="region" placeholder="Region/Province" value="{{ old('region', $address->region) }}" class="w-full rounded-lg border-stone-200 text-sm">
            <input name="postal_code" placeholder="Postal code" value="{{ old('postal_code', $address->postal_code) }}" class="w-full rounded-lg border-stone-200 text-sm">
            <input name="phone" placeholder="Phone" value="{{ old('phone', $address->phone) }}" class="w-full rounded-lg border-stone-200 text-sm">
            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" name="is_default" value="1" {{ $address->is_default ? 'checked' : '' }} class="rounded border-stone-300"> Set as default
            </label>
            @if ($errors->any())
                <ul class="text-sm text-red-600 list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <button class="w-full rounded-full bg-orange-500 text-white text-sm py-2.5">Update address</button>
        </form>
    </div>
@endsection
