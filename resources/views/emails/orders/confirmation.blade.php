<h1>Thanks for your FarSell order</h1>
<p>Hi {{ $order->guest_name ?: 'there' }},</p>
<p>We received order <strong>{{ $order->number }}</strong>.</p>

<table role="presentation" cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;width:100%;text-align:left">
    <thead>
        <tr>
            <th>Item</th>
            <th>SKU</th>
            <th>Qty</th>
            <th>Unit price</th>
            <th>Line total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->items as $item)
            <tr>
                <td>
                    {{ $item->name }}
                    @if($item->variant_options)
                        <br><small>{{ collect($item->variant_options)->map(fn($value, $name) => $name.': '.$value)->join(', ') }}</small>
                    @endif
                </td>
                <td>{{ $item->product_sku ?? 'Unavailable' }}</td>
                <td>{{ $item->qty }}</td>
                <td>PHP {{ number_format((float) $item->unit_price, 2) }}</td>
                <td>PHP {{ number_format((float) $item->line_total, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p>Subtotal: PHP {{ number_format((float) $order->subtotal, 2) }}<br>
Shipping: PHP {{ number_format((float) $order->shipping_fee, 2) }}<br>
<strong>Total: PHP {{ number_format((float) $order->total, 2) }}</strong></p>
<p>Payment method: {{ $order->payment_method === \App\Enums\PaymentMethod::Cod ? 'Cash on delivery' : 'Card / e-wallet (demo)' }}</p>
<p><a href="{{ route('orders.show', $order) }}">View order and print invoice or packing slip</a></p>
