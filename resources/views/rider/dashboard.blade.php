@extends('layouts.portal')

@section('title', 'Rider Dashboard · FarSell')

@section('content')
    <section class="fs-card-raised p-5 sm:p-7">
        <p class="text-xs font-semibold uppercase tracking-widest text-accent">Rider Dashboard</p>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Your delivery workspace</h1>
            @if ($canViewDeliveries)
                <x-ui.button variant="primary" size="lg" :href="route('fulfillments.index')">Manage pickups and deliveries</x-ui.button>
            @endif
        </div>
        <p class="mt-2 text-sm text-text-muted">Welcome, {{ auth()->user()->name }}. Review your application and assigned deliveries here.</p>
        @if($profile)
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-ui.status-pill :status="$profile->status->value">Application: {{ ucfirst($profile->status->value) }}</x-ui.status-pill>
                <span class="text-sm text-text-muted">{{ ucfirst($profile->vehicle_type) }} · {{ $profile->city }}</span>
                <x-ui.button variant="link" :href="route('rider.profile')">View rider profile</x-ui.button>
            </div>
        @endif
    </section>

    @if(!$canViewDeliveries)
        <section class="fs-card mt-5 p-5">
            @if(!$profile)
                <h2 class="font-semibold">Rider profile not found</h2>
                <p class="mt-2 text-sm text-text-muted">Your account has rider access but no application profile. Contact an administrator to repair the account.</p>
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
        @if(auth()->user()->role === \App\Enums\UserRole::Rider)
            <section id="delivery-requests" class="fs-card p-5 mt-5 scroll-mt-32">
                <h2 class="font-semibold">Delivery requests</h2>
                <p class="text-sm text-text-muted mt-2">Requests match your registered city: {{ $profile->city }}. Going unavailable does not cancel accepted deliveries.</p>
                <form method="post" action="{{ route('rider.availability') }}" class="my-4">
                    @csrf
                    <p class="text-sm mb-2">You are currently {{ $profile->is_available ? 'available' : 'unavailable' }}.</p>
                    <x-ui.button variant="secondary" type="submit" name="is_available" value="{{ $profile->is_available ? '0' : '1' }}">{{ $profile->is_available ? 'Go unavailable' : 'Go available' }}</x-ui.button>
                </form>
                @if($errors->any())<div role="alert" class="text-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
                <p data-request-refresh-status class="text-xs text-text-muted mb-3">Loading delivery requests…</p>
                <div data-delivery-requests data-url="{{ route('rider.delivery-requests') }}"></div>
                <noscript><p>Enable JavaScript to receive live delivery requests.</p></noscript>
            </section>
        @endif
        <section aria-label="Delivery metrics" class="my-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
            @foreach($stats as $label => $count)
                <x-dashboard.kpi-card :data="['label' => $label, 'value' => number_format($count)]" :comparison="false" />
            @endforeach
        </section>

        <section class="fs-card mb-5 p-5" aria-labelledby="rider-feedback-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="rider-feedback-heading" class="scroll-mt-32 font-semibold">Buyer delivery feedback</h2>
                    <p class="mt-1 text-sm text-text-muted">{{ $ratingStats->total }} {{ (int) $ratingStats->total === 1 ? 'rating' : 'ratings' }} · {{ $ratingStats->total ? number_format((float) $ratingStats->average, 1).' / 5 average' : 'No ratings yet' }}</p>
                </div>
                <x-ui.button variant="link" :href="route('rider.history.index')">View delivery history</x-ui.button>
            </div>
            @if($recentRatings->isNotEmpty())
                <ul class="mt-3 space-y-2">
                    @foreach($recentRatings as $rating)
                        <li class="rounded-lg border border-surface-border p-3 text-sm">
                            <span class="font-medium">{{ $rating->rating }} / 5</span>
                            @if($rating->tags)<p class="mt-1 text-xs text-text-muted">{{ implode(' · ', $rating->tags) }}</p>@endif
                            @if($rating->feedback)<p class="mt-1 text-text-muted">{{ $rating->feedback }}</p>@else<p class="mt-1 text-text-muted">No written comment.</p>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section aria-labelledby="rider-deliveries" class="fs-card p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 id="rider-deliveries" class="scroll-mt-32 font-semibold">Your active deliveries</h2>
                    <p class="mt-1 text-xs text-text-muted">Listed by order creation date, oldest first; this is not an optimized delivery route.</p>
                </div>
                <x-action-menu label="Filters" context="for active deliveries" class="ml-auto">
                <form method="get" action="{{ route('rider.dashboard') }}#rider-deliveries" class="space-y-3 p-2">
                    <label for="delivery-status" class="text-sm">Status</label>
                    <select id="delivery-status" name="status" class="fs-input w-full">
                        <option value="">All active</option>
                        <option value="assigned" @selected($selectedStatus === 'assigned')>Assigned</option>
                        <option value="in_transit" @selected($selectedStatus === 'in_transit')>In transit</option>
                        <option value="delivery_failed" @selected($selectedStatus === 'delivery_failed')>Failed attempt</option>
                        <option value="returning" @selected($selectedStatus === 'returning')>Returning to seller</option>
                    </select>
                    <x-ui.button variant="primary" size="lg" type="submit" class="w-full">Apply filters</x-ui.button>
                </form>
                </x-action-menu>
            </div>
            @if($selectedStatus)
                <x-ui.button variant="link" href="{{ route('rider.dashboard') }}#rider-deliveries" class="mt-2 bg-surface-muted" aria-label="Remove delivery status filter">
                    {{ \App\Enums\FulfillmentStatus::from($selectedStatus)->label() }} <x-ui.icon name="close" size="sm" class="ml-2" />
                </x-ui.button>
            @endif
            @error('status')
                <p role="alert" class="mt-3 text-sm text-error">{{ $message }}</p>
            @enderror
            <div class="mt-4 space-y-3">
                @foreach($shipments as $shipment)
                    <article class="rounded-xl border border-surface-border p-4">
                        <h3 class="font-semibold">{{ $shipment->order->number }} · {{ $shipment->shop_name }}</h3>
                        <p class="mt-2 text-sm">{{ $shipment->status->label() }}</p>
                        <x-ui.button variant="link" :href="route('fulfillments.index')">Open pickup and delivery controls</x-ui.button>
                    </article>
                @endforeach
                {{ $shipments->links() }}
                @forelse($deliveries as $delivery)
                    <article class="rounded-xl border border-surface-border p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-semibold text-sm">Order {{ $delivery->number }}</h3>
                            <x-ui.status-pill :status="$delivery->status->value">{{ $delivery->status->label() }}</x-ui.status-pill>
                        </div>
                        <p class="mt-1 text-xs text-text-muted">Order placed <time datetime="{{ $delivery->created_at->toIso8601String() }}">{{ $delivery->created_at->format('M j, Y · g:i A') }}</time></p>
                        <h4 class="mt-3 text-xs font-semibold text-text-muted">Delivery address / contact supplied at checkout</h4>
                        <p class="mt-1 text-sm break-words whitespace-pre-line">{{ $delivery->ship_to }}</p>
                    </article>
                @empty
                    @if($shipments->isEmpty())<p class="py-6 text-sm text-text-muted">{{ $selectedStatus ? 'No deliveries match this status.' : 'No active deliveries assigned to you yet.' }}</p>@endif
                @endforelse
            </div>
            @if($deliveries->hasPages())
                <div class="mt-4">{{ $deliveries->links() }}</div>
            @endif
            <p class="mt-4 text-xs text-text-muted">Addresses are shown only for your active assignments. Completed deliveries are counted without exposing past delivery details.</p>
        </section>
    @endif

    <p class="mt-4 text-xs text-text-muted">Open Manage pickups and deliveries to record shipment progress and COD collection. Location tracking is not available.</p>
@endsection

@push('scripts')
    <script src="{{ asset('js/rider-requests.js') }}" defer></script>
@endpush
