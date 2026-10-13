@extends('layouts.app')

@section('title', 'Saved Items · FarSell')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Saved Items</h1>

    @if ($saved->isNotEmpty())
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach ($saved as $savedItem)
                @if ($savedItem->product)
                    @include('catalog.partials.card', ['product' => $savedItem->product])
                @endif
            @endforeach
        </div>

        <div class="mt-6">
            {{ $saved->links() }}
        </div>
    @else
        <div class="text-center py-16 text-text-muted">
            <p class="mb-3">No saved items yet.</p>
            <a href="{{ route('catalog.index') }}" class="text-accent underline text-sm">Browse the catalogue</a>
        </div>
    @endif
@endsection
