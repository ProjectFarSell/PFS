@extends('layouts.portal')

@section('title', 'Barangay assignments · FarSell')

@section('content')
    <a href="{{ route('admin.dashboard') }}" class="text-sm text-accent underline">Back to Admin Dashboard</a>
    <section class="fs-card mt-4 max-w-3xl p-5">
        <h1 class="text-xl font-semibold">Barangay assignments</h1>
        <p class="mt-2 text-sm text-text-muted">Assign one approved rider to each barangay. Ready shipments are sent directly to that rider.</p>

        <form method="get" action="{{ route('admin.riders.barangays.index') }}" class="mt-5 flex flex-wrap items-end gap-3">
            <label for="rider-profile" class="block min-w-64 flex-1 text-sm">Approved rider
                <select id="rider-profile" name="rider_profile_id" class="mt-1 w-full rounded-lg" required>
                    <option value="">Select a rider</option>
                    @foreach($riders as $rider)
                        <option value="{{ $rider->id }}" @selected($profile?->id === $rider->id)>{{ $rider->user->name }} · {{ $rider->city }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn-outline">Manage rider</button>
        </form>

        @if($profile)
            <section class="mt-6 border-t border-surface-border pt-5">
                <h2 class="font-semibold">{{ $profile->user->name }}</h2>
                <p class="mt-1 text-sm text-text-muted">Coverage in {{ $profile->city }}</p>
                <form method="post" action="{{ route('admin.riders.barangays.store', $profile) }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="block min-w-64 flex-1 text-sm">Barangay
                        <select name="psgc_barangay_id" class="mt-1 w-full rounded-lg" required>
                            <option value="">Select a barangay</option>
                            @foreach($barangays as $barangay)
                                <option value="{{ $barangay->id }}" @selected(old('psgc_barangay_id') == $barangay->id)>{{ $barangay->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="btn-accent">Assign barangay</button>
                </form>
                @error('psgc_barangay_id')<p role="alert" class="mt-2 text-sm text-error">{{ $message }}</p>@enderror

                <ul class="mt-4 space-y-2 text-sm">
                    @forelse($profile->barangayAssignments as $assignment)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-surface-border px-3 py-2">
                            <span>{{ $assignment->barangay->name }}{{ $assignment->barangay->cityMunicipality ? ', '.$assignment->barangay->cityMunicipality->name : '' }}</span>
                            <form method="post" action="{{ route('admin.riders.barangays.destroy', [$profile, $assignment]) }}">
                                @csrf @method('delete')
                                <button class="text-sm text-error underline">Remove</button>
                            </form>
                        </li>
                    @empty
                        <li class="text-text-muted">No barangays assigned.</li>
                    @endforelse
                </ul>
            </section>
        @elseif($riders->isEmpty())
            <p class="mt-5 text-sm text-text-muted">No approved rider accounts are available yet.</p>
        @endif
    </section>
@endsection
