@extends('layouts.app')

@section('title', 'My addresses · FarSell')

@section('content')
    <div class="max-w-lg mx-auto">
        <div class="flex items-center justify-between mb-3">
            <h1 class="text-lg font-semibold">My addresses</h1>
            <a href="{{ route('account.addresses.create') }}" class="text-sm text-orange-600">Add new</a>
        </div>

        @if (session('status'))
            <p class="rounded-lg bg-emerald-50 text-emerald-800 text-sm px-3 py-2 mb-3">{{ session('status') }}</p>
        @endif
        @forelse ($addresses as $address)
            <div class="rounded-xl bg-white border border-stone-200 p-4 mb-3">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium">
                            {{ $address->label }}
                            @if ($address->is_default)
                                <span class="ml-1 text-[10px] uppercase text-orange-600">Default</span>
                            @endif
                        </p>
                        <p class="text-sm text-stone-600 mt-1">{{ $address->line1 }}</p>
                        <p class="text-sm text-stone-500">{{ $address->city }}{{ $address->region ? ', '.$address->region : '' }} {{ $address->postal_code }}</p>
                        @if ($address->phone)
                            <p class="text-xs text-stone-400 mt-1">{{ $address->phone }}</p>
                        @endif
                    </div>
                    <div class="flex gap-2 text-xs">
                        <a href="{{ route('account.addresses.edit', $address) }}" class="text-orange-600">Edit</a>
                        <form method="post" action="{{ route('account.addresses.destroy', $address) }}" onsubmit="return confirm('Remove this address?')">
                            @csrf
                            @method('delete')
                            <button class="text-stone-400">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-sm text-stone-500">No saved addresses yet.</p>
        @endforelse
    </div>
@endsection
