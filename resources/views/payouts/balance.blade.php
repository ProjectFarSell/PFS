<dl class="mt-4 grid gap-4 sm:grid-cols-3">
    @foreach(['available_cents' => 'Available balance', 'in_unpaid_batch_cents' => 'Included in unpaid batch', 'ineligible_held_cents' => 'Held credits'] as $key => $label)
        <div><dt class="text-sm text-text-muted">{{ $label }}</dt><dd class="text-lg font-semibold">PHP {{ \App\Support\Money::decimal($balance[$key]) }}</dd></div>
    @endforeach
</dl>
<p class="mt-3 text-sm text-text-muted">Available balance includes eligible entries already in an unpaid batch. Refunds can reduce it below zero.</p>
