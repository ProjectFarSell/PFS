@extends('layouts.portal')

@section('title', 'Partners · FarSell')

@section('content')
    <x-ui.button variant="link" :href="route('admin.dashboard')" class="text-sm text-accent underline">Back to Admin Dashboard</x-ui.button>
    <section class="fs-card mt-4 p-5">
        <h1 class="text-xl font-semibold">Partners</h1>
        <p class="mt-2 text-sm text-text-muted">Monitor rider and seller applications, then open a record to review it.</p>

        <nav class="mt-5 flex border-b border-surface-border" aria-label="Partner type">
            <x-ui.button variant="link" :href="route('admin.partners.index', ['tab' => 'riders'])" class="border-b-2 px-4 py-2 font-semibold {{ $tab === 'riders' ? 'border-accent text-accent' : 'border-transparent text-text-muted' }}">Riders</x-ui.button>
            <x-ui.button variant="link" :href="route('admin.partners.index', ['tab' => 'sellers'])" class="border-b-2 px-4 py-2 font-semibold {{ $tab === 'sellers' ? 'border-accent text-accent' : 'border-transparent text-text-muted' }}">Sellers</x-ui.button>
        </nav>

        <form method="get" action="{{ route('admin.partners.index') }}" class="my-5 flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label for="partner-status" class="text-sm">Status</label>
            <select id="partner-status" name="status" class="rounded-lg text-sm">
                <option value="">All statuses</option>
                @foreach($statuses as $option)
                    @php($value = $option instanceof \BackedEnum ? $option->value : $option)
                    <option value="{{ $value }}" @selected($status === $value)>{{ ucfirst($value) }}</option>
                @endforeach
            </select>
            <x-ui.button variant="secondary" type="submit">Filter</x-ui.button>
        </form>

        <div class="divide-y divide-surface-border">
            @forelse($records as $record)
                @if($tab === 'riders')
                    <article class="relative flex flex-wrap items-center justify-between gap-4 py-4">
                        <div class="min-w-0">
                            <h2 class="font-semibold">{{ $record->user->name }}</h2>
                            <p class="mt-1 text-sm text-text-muted">{{ $record->city }} · {{ ucfirst($record->vehicle_type) }} · {{ $record->user->email }}</p>
                        </div>
                        <div class="ml-auto flex items-center gap-3">
                            <x-ui.status-pill :status="$record->status->value" />
                            <x-ui.button variant="link" :href="route('admin.riders.show', $record)" class="after:absolute after:inset-0 after:content-['']">Open rider</x-ui.button>
                        </div>
                    </article>
                @else
                    <article class="relative flex flex-wrap items-center justify-between gap-4 py-4">
                        <div class="min-w-0">
                            <h2 class="font-semibold">{{ $record->shop_name }}</h2>
                            <p class="mt-1 text-sm text-text-muted">{{ $record->user->name }} · {{ $record->city }} · {{ $record->user->email }}</p>
                        </div>
                        <div class="ml-auto flex items-center gap-3">
                            <x-ui.status-pill :status="$record->status" />
                            <x-ui.button variant="link" :href="route('admin.sellers.show', $record)" class="after:absolute after:inset-0 after:content-['']">Open seller</x-ui.button>
                        </div>
                    </article>
                @endif
            @empty
                <p class="py-7 text-sm text-text-muted">No {{ $status ?: '' }} {{ $tab }} found.</p>
            @endforelse
        </div>
        <div class="mt-5">{{ $records->links() }}</div>
    </section>
@endsection
