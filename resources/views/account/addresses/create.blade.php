@extends('layouts.app')

@section('title', 'Add address · FarSell')

@section('content')
    <div class="max-w-lg mx-auto rounded-2xl bg-white border border-stone-200 p-5">
        <h1 class="text-lg font-semibold mb-3">Add address</h1>
        <form method="post" action="{{ route('account.addresses.store') }}" class="space-y-3">
            @csrf
            <input name="label" placeholder="Label (e.g. Home, Work)" value="{{ old('label') }}" class="w-full rounded-lg border-stone-200 text-sm">
            <input name="line1" required placeholder="Street address" value="{{ old('line1') }}" class="w-full rounded-lg border-stone-200 text-sm">
            <input name="city" required placeholder="City" value="{{ old('city') }}" class="w-full rounded-lg border-stone-200 text-sm">
            <input name="region" placeholder="Region/Province" value="{{ old('region') }}" class="w-full rounded-lg border-stone-200 text-sm">
            <input name="postal_code" placeholder="Postal code" value="{{ old('postal_code') }}" class="w-full rounded-lg border-stone-200 text-sm">
            <input name="phone" placeholder="Phone" value="{{ old('phone') }}" class="w-full rounded-lg border-stone-200 text-sm">
            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" name="is_default" value="1" class="rounded border-stone-300"> Set as default
            </label>
            @if ($errors->any())
                <ul class="text-sm text-red-600 list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <button class="w-full rounded-full bg-orange-500 text-white text-sm py-2.5">Save address</button>
        </form>
    </div>
@endsection
