@extends('layouts.app')

@section('title', 'Rider profile · FarSell')

@section('content')
    <div class="max-w-lg mx-auto rounded-2xl bg-white border border-stone-200 p-5">
        <p class="text-xs uppercase tracking-wide text-orange-600">Courier card</p>
        <h1 class="text-xl font-semibold mt-1">{{ auth()->user()->name }}</h1>
        <p class="text-sm text-stone-500">{{ $profile->city }} · {{ $profile->vehicle_type }}</p>
        <p class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-medium
            {{ $profile->status->value === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">
            {{ ucfirst($profile->status->value) }}
        </p>
        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-stone-500">Plate</dt>
                <dd>{{ $profile->plate_number ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-stone-500">License</dt>
                <dd>{{ $profile->license_no }}</dd>
            </div>
        </dl>
        @if ($profile->bio)
            <p class="text-sm text-stone-600 mt-4">{{ $profile->bio }}</p>
        @endif
        <a href="{{ route('rider.register') }}" class="inline-block mt-4 text-sm text-orange-600">Update application</a>
    </div>
@endsection
