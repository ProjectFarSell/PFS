@extends('layouts.app')

@section('title', 'Rider Dashboard · FarSell')

@section('content')
    <section class="fs-card-raised p-5 sm:p-7">
        <p class="text-xs font-semibold uppercase tracking-widest text-accent">Rider Dashboard</p>
        <h1 class="mt-2 text-2xl font-semibold">Your delivery workspace</h1>
        <p class="mt-2 text-sm text-text-muted">Welcome, {{ auth()->user()->name }}. Review your application and assigned deliveries here.</p>
        @if($profile)
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <span class="badge {{ $profile->isApproved() ? 'badge-success' : 'badge-neutral' }}">Application: {{ ucfirst($profile->status->value) }}</span>
                <span class="text-sm text-text-muted">{{ ucfirst($profile->vehicle_type) }} · {{ $profile->city }}</span>
                <a href="{{ route('rider.profile') }}" class="text-sm text-accent underline">View rider profile</a>
            </div>
        @endif
    </section>

    @if(!$canViewDeliveries)
        <section class="fs-card mt-5 p-5">
            @if(!$profile)
                <h2 class="font-semibold">Complete your rider application</h2>
                <p class="mt-2 text-sm text-text-muted">Submit your rider details for review. Applying does not automatically grant delivery access.</p>
                <a href="{{ route('rider.register') }}" class="btn-accent mt-4">Apply as a rider</a>
            @elseif($profile->status === \App\Enums\RiderStatus::Pending)
                <h2 class="font-semibold">Application pending review</h2>
                <p class="mt-2 text-sm text-text-muted">Delivery details will be available after approval and rider access are granted.</p>
            @elseif($profile->status === \App\Enums\RiderStatus::Rejected)
                <h2 class="font-semibold">Application not approved</h2>
                <p class="mt-2 text-sm text-text-muted">Contact the project administrator about the review before updating your application.</p>
            @elseif($profile->status === \App\Enums\RiderStatus::Suspended)
                <h2 class="font-semibold">Rider access suspended</h2>
                <p class="mt-2 text-sm text-text-muted">Delivery information is unavailable. Contact the project administrator.</p>
            @else
                <h2 class="font-semibold">Rider account activation needed</h2>
                <p class="mt-2 text-sm text-text-muted">Your application is approved, but your account has not been granted rider access. Contact the project administrator.</p>
            @endif
        </section>
    @else
        <dl class="my-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
            @foreach($stats as $label => $count)
                <div class="fs-card p-4">
                    <dt class="text-xs text-text-muted">{{ $label }}</dt>
                    <dd class="mt-2 text-2xl font-semibold">{{ number_format($count) }}</dd>
                </div>
            @endforeach
        </dl>

        <section aria-labelledby="rider-deliveries" class="fs-card p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 id="rider-deliveries" class="font-semibold">Your active deliveries</h2>
                    <p class="mt-1 text-xs text-text-muted">Listed by order creation date, oldest first; this is not an optimized delivery route.</p>
                </div>
                <form method="get" action="{{ route('rider.dashboard') }}" class="flex flex-wrap items-center gap-2">
                    <label for="delivery-status" class="text-sm">Status</label>
                    <select id="delivery-status" name="status" class="rounded-lg text-sm">
                        <option value="">All active</option>
                        <option value="assigned" @selected($selectedStatus === 'assigned')>Assigned</option>
                        <option value="in_transit" @selected($selectedStatus === 'in_transit')>In transit</option>
                    </select>
                    <button class="btn-accent">Filter</button>
                    @if($selectedStatus)
                        <a href="{{ route('rider.dashboard') }}" class="text-sm text-accent underline">Clear</a>
                    @endif
                </form>
            </div>
            @error('status')
                <p role="alert" class="mt-3 text-sm text-error">{{ $message }}</p>
            @enderror
            <div class="mt-4 space-y-3">
                @forelse($deliveries as $delivery)
                    <article class="rounded-xl border border-surface-border p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-semibold text-sm">Order {{ $delivery->number }}</h3>
                            <span class="badge badge-accent">{{ $delivery->status->label() }}</span>
                        </div>
                        <p class="mt-1 text-xs text-text-muted">Order placed <time datetime="{{ $delivery->created_at->toIso8601String() }}">{{ $delivery->created_at->format('M j, Y · g:i A') }}</time></p>
                        <h4 class="mt-3 text-xs font-semibold text-text-muted">Delivery address / contact supplied at checkout</h4>
                        <p class="mt-1 text-sm break-words whitespace-pre-line">{{ $delivery->ship_to }}</p>
                    </article>
                @empty
                    <p class="py-6 text-sm text-text-muted">{{ $selectedStatus ? 'No deliveries match this status.' : 'No active deliveries assigned to you yet.' }}</p>
                @endforelse
            </div>
            @if($deliveries->hasPages())
                <div class="mt-4">{{ $deliveries->links() }}</div>
            @endif
            <p class="mt-4 text-xs text-text-muted">Addresses are shown only for your active assignments. Completed deliveries are counted without exposing past delivery details.</p>
        </section>
    @endif

    <p class="mt-4 text-xs text-text-muted">Read-only dashboard. Applications are reviewed by an administrator. Dispatch, status updates, live tracking, and payment collection controls are not implemented yet.</p>
@endsection
