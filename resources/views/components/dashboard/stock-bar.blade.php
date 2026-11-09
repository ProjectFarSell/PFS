@props(['data' => [], 'dataExpression' => null])

<div class="min-w-24" x-data="{ get stock() { return {{ $dataExpression ?? IlluminateSupportJs::from($data)->toHtml() }} } }">
    <p class="text-sm font-semibold tabular-nums" x-text="stock.quantity + ' units'"></p>
    <div class="mt-1 h-2 overflow-hidden rounded-full bg-surface-muted" aria-hidden="true">
        <div class="h-full rounded-full" :class="stock.quantity <= stock.reorderPoint ? 'bg-red-700 dark:bg-red-400' : 'bg-emerald-700 dark:bg-emerald-400'"
             :style="{ width: Math.min(100, stock.quantity / Math.max(1, stock.capacity) * 100) + '%' }"></div>
    </div>
    <p class="mt-1 text-xs text-text-muted" x-text="stock.quantity === 0 ? 'Out of stock' : stock.quantity <= stock.reorderPoint ? 'Reorder needed' : 'Stock available'"></p>
</div>
