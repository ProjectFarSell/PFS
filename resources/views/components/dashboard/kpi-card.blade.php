@props(['data' => [], 'dataExpression' => null, 'featuredExpression' => 'false', 'comparison' => true])

<article class="fs-card min-w-0 p-3 sm:p-4"
    @if($dataExpression || $comparison)
        data-dashboard-widgets x-data="{ get kpi() { return {{ $dataExpression ?? IlluminateSupportJs::from($data)->toHtml() }} } }"
        :class="{ 'border-accent bg-accent-subtle': {{ $featuredExpression }} }"
    @endif>
    <h3 class="text-sm font-semibold text-text-muted" @if($dataExpression) x-text="kpi.label" @endif>{{ $data['label'] ?? '' }}</h3>
    <p class="dashboard-kpi-value mt-2 break-words text-lg font-bold tabular-nums sm:text-2xl"
       @if($dataExpression) :class="{ 'sm:text-3xl xl:text-4xl': {{ $featuredExpression }} }" x-text="$dashboardMetric(kpi.value, kpi.unit)" @endif>{{ $data['value'] ?? '' }}</p>
    @if($comparison)
    <p class="mt-2 text-xs font-semibold"
       :class="kpi.change > 0 ? 'text-emerald-800 dark:text-emerald-300' : kpi.change < 0 ? 'text-red-800 dark:text-red-300' : 'text-text-muted'">
        <x-ui.icon name="increase" size="sm" x-show="kpi.change > 0" class="inline-block align-text-bottom" />
        <x-ui.icon name="decrease" size="sm" x-show="kpi.change < 0" class="inline-block align-text-bottom" />
        <span x-text="kpi.change == null ? 'No percentage comparison' : kpi.change === 0 ? '0% change' : (kpi.change > 0 ? 'Up ' : 'Down ') + $dashboardMetric(Math.abs(kpi.change), 'percent')"></span>
        <span class="block font-normal text-text-muted">vs. previous period</span>
    </p>
    @endif
    @if($dataExpression || isset($data['note']))
        <p class="mt-1 text-xs text-text-muted" @if($dataExpression) x-show="kpi.note" x-text="kpi.note" @endif>{{ $data['note'] ?? '' }}</p>
    @endif
    {{ $slot }}
</article>
