@if($errors->any())<div role="alert" class="mt-4 text-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<p class="mt-3 font-semibold">Total after rejected shipments: ₱{{ number_format($order->fulfillments->sum(fn ($part) => $part->amount()), 2) }}</p>
<p class="mt-2 text-xs text-text-muted">Each shop prepares and delivers its items separately. Delivery fees are split between shipments; a rejected shipment's fee is waived. Overall status follows the least advanced active shipment.</p>
@if($order->payment_method === \App\Enums\PaymentMethod::GatewayStub)<p class="mt-2 text-xs text-text-muted">Demo payment only. No real charge or refund is processed.</p>@endif
<div class="mt-5 space-y-4">
    @foreach($order->fulfillments as $part)
        <section class="fs-card p-5">
            <div class="flex flex-wrap justify-between gap-3"><h2 class="font-semibold">{{ $part->shop_name }} · Shipment #{{ $part->id }}</h2><span class="badge badge-neutral">{{ $part->status->label() }}</span></div>
            <ul class="mt-3 text-sm space-y-2">@foreach($part->items as $item)<li class="flex justify-between gap-3"><span>{{ $item->name }}@if($item->variant_options) ({{ collect($item->variant_options)->map(fn($value, $name) => $name.': '.$value)->join(', ') }})@endif × {{ $item->qty }}</span><span>₱{{ number_format((float) $item->line_total, 2) }}</span></li>@endforeach</ul>
            <p class="mt-3 text-sm">Shipment total: ₱{{ number_format($part->amount(), 2) }} (delivery allocation ₱{{ number_format((float) $part->shipping_fee, 2) }})</p>
            @if($part->rider)<p class="mt-2 text-sm">Rider: {{ $part->rider->user?->name ?? 'Unavailable account' }}</p>@endif
            @if($part->rejection_reason)<p class="mt-2 text-sm text-error">Reason: {{ $part->rejection_reason }}</p>@endif
            @if($part->cod_collected_at)<p class="mt-2 text-sm">COD collected: ₱{{ number_format($part->amount(), 2) }}</p>@endif
            <ol class="mt-5 text-sm" aria-label="Shipment history">
                @foreach($part->events as $event)
                    @php
                        $isCurrent = $loop->last && !in_array($part->status, [\App\Enums\FulfillmentStatus::Completed, \App\Enums\FulfillmentStatus::Rejected], true);
                        $isRejected = $event->status === 'rejected';
                    @endphp
                    <li class="relative flex gap-4 {{ $loop->last ? '' : 'pb-6' }}" @if($isCurrent) aria-current="step" @endif>
                        <div class="relative flex w-5 shrink-0 justify-center" aria-hidden="true">
                            @unless($loop->last)
                                <span class="absolute left-1/2 top-5 -bottom-6 w-px -translate-x-1/2 bg-accent/40"></span>
                            @endunless
                            <span class="relative z-10 mt-0.5 h-5 w-5 rounded-full border-2 {{ $isRejected ? 'border-error bg-error' : ($isCurrent ? 'border-accent bg-surface ring-4 ring-accent/15' : 'border-accent bg-accent') }}"></span>
                        </div>
                        <div class="min-w-0">
                            <p class="font-medium {{ $isCurrent ? 'text-accent' : '' }}">{{ \App\Enums\FulfillmentStatus::from($event->status)->label() }}</p>
                            @if($isCurrent)<span class="sr-only">Current step.</span>@endif
                            <time class="text-xs text-text-muted" datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('M j, Y g:i A') }}</time>
                            @if($event->note)<p class="mt-1 break-words text-text-muted">{{ $event->note }}</p>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
            @if($part->status === \App\Enums\FulfillmentStatus::Delivered && auth()->id() === $order->user_id)
                <form method="post" action="{{ route('fulfillments.update', $part) }}" class="mt-4">@csrf <p class="mb-3 text-sm">Confirm only after receiving this shop's items.</p><button name="action" value="complete" class="btn-accent">Confirm received / Complete shipment</button></form>
            @endif
        </section>
    @endforeach
</div>
