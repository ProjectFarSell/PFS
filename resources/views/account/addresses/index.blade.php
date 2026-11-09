@extends('layouts.app')

@section('title', 'My addresses · FarSell')

@section('content')
    <div class="max-w-lg mx-auto">
        <a href="{{ route('account.profile') }}" class="inline-block text-sm text-accent hover:text-accent mb-3"><x-ui.icon name="previous" size="sm" class="inline-block align-text-bottom" /> My Profile</a>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-lg font-semibold">My addresses</h1>
            <x-ui.button variant="primary" size="lg" :href="route('account.addresses.create')">Add address</x-ui.button>
        </div>

        @forelse ($addresses as $address)
            <div class="rounded-xl bg-surface border border-surface-border p-4 mb-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium">
                            {{ $address->label }}
                            @if ($address->is_default)
                                <span class="ml-1 text-[10px] uppercase text-accent font-semibold">Default</span>
                            @endif
                        </p>
                        <p class="text-sm text-text-muted mt-1">{{ $address->line1 }}</p>
                        <p class="text-sm text-text-muted">{{ $address->city }}{{ $address->region ? ', '.$address->region : '' }} {{ $address->postal_code }}</p>
                        @if ($address->phone)
                            <p class="text-xs text-text-muted mt-1">{{ $address->phone }}</p>
                        @endif
                    </div>
                    <div class="ml-auto flex flex-wrap gap-4">
                        <x-ui.button variant="link" :href="route('account.addresses.edit', $address)">Edit</x-ui.button>
                        <form method="post" action="{{ route('account.addresses.destroy', $address) }}">
                            @csrf
                            @method('delete')
                            <x-destructive-confirmation name="Delete address" :description="'Remove '.$address->label.' from your saved addresses.'" />
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-sm text-text-muted">No saved addresses yet.</p>
        @endforelse
    </div>
@endsection
