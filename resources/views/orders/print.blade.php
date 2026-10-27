<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document === 'invoice' ? 'Invoice' : 'Packing slip' }} {{ $order->number }} · FarSell</title>
    <style>
        body { color: #222; font: 14px/1.5 Arial, sans-serif; margin: 2rem auto; max-width: 850px; padding: 0 1rem; }
        h1 { margin-bottom: .25rem; }
        .muted { color: #666; }
        .controls { display: flex; gap: .75rem; margin: 1.5rem 0; }
        .controls a, .controls button { background: #6d28d9; border: 0; border-radius: 6px; color: white; cursor: pointer; padding: .65rem 1rem; text-decoration: none; }
        table { border-collapse: collapse; margin-top: 1.5rem; width: 100%; }
        th, td { border-bottom: 1px solid #ddd; padding: .7rem .5rem; text-align: left; vertical-align: top; }
        th:last-child, td:last-child { text-align: right; }
        .totals { margin: 1.25rem 0 0 auto; max-width: 280px; }
        .totals p { display: flex; justify-content: space-between; margin: .35rem 0; }
        @media print { body { margin: 0; max-width: none; } .controls { display: none; } }
    </style>
</head>
<body>
    <div class="controls">
        <button type="button" onclick="window.print()">Print {{ $document === 'invoice' ? 'invoice' : 'packing slip' }}</button>
        <a href="{{ route('orders.show', $order) }}">Back to order</a>
    </div>

    @if(!empty($invoiceGenerationFailed))
        <p class="muted">The PDF service was unavailable, so this invoice is shown in the browser’s print-ready format.</p>
    @elseif($document === 'invoice' && blank(config('services.invoice_generator.api_key')))
        <p class="muted">The PDF service is not configured yet, so this invoice is shown in the browser’s print-ready format.</p>
    @endif

    <h1>{{ $document === 'invoice' ? 'Invoice' : 'Packing slip' }}</h1>
    <p class="muted">FarSell · Order {{ $order->number }} · {{ $order->created_at->format('M j, Y') }}</p>
    <p><strong>Ship to</strong><br>{{ $order->guest_name }}<br>{{ $order->ship_to }}</p>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>SKU</th>
                <th>Qty</th>
                @if($document === 'invoice')<th>Unit price</th><th>Line total</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->name }}@if($item->variant_options)<br><span class="muted">{{ collect($item->variant_options)->map(fn($value, $name) => $name.': '.$value)->join(', ') }}</span>@endif</td>
                    <td>{{ $item->product_sku ?? 'Unavailable' }}</td>
                    <td>{{ $item->qty }}</td>
                    @if($document === 'invoice')
                        <td>PHP {{ number_format((float) $item->unit_price, 2) }}</td>
                        <td>PHP {{ number_format((float) $item->line_total, 2) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($document === 'invoice')
        <div class="totals">
            <p><span>Subtotal</span><span>PHP {{ number_format((float) $order->subtotal, 2) }}</span></p>
            <p><span>Shipping</span><span>PHP {{ number_format((float) $order->shipping_fee, 2) }}</span></p>
            <p><strong>Total</strong><strong>PHP {{ number_format((float) $order->total, 2) }}</strong></p>
        </div>
    @endif
</body>
</html>
