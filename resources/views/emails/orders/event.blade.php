<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $delivery->payload['title'] }}</title></head>
<body style="margin:0;background:#f5f3ff;color:#11111b;font-family:Arial,sans-serif;">
    <main style="max-width:600px;margin:24px auto;padding:28px;background:#fff;border:1px solid #ddd6fe;border-radius:12px;">
        <p style="color:#7c3aed;font-size:20px;font-weight:bold;">FarSell</p>
        <h1 style="font-size:22px;">{{ $delivery->payload['title'] }}</h1>
        <p>Hi {{ $delivery->recipient_name ?: 'there' }},</p>
        <p style="line-height:1.6;">{{ $delivery->payload['message'] }}</p>
        <p><strong>Order:</strong> {{ $delivery->payload['order_number'] }}<br><strong>Shop:</strong> {{ $delivery->payload['shop_name'] }}</p>
        @if(!empty($delivery->payload['status_label']))<p><strong>Shipment status:</strong> {{ $delivery->payload['status_label'] }}</p>@endif
        @if(!empty($delivery->payload['detail']))<p style="white-space:pre-line;">{{ $delivery->payload['detail'] }}</p>@endif
        <p style="color:#6b7280;font-size:13px;">Update recorded {{ \Illuminate\Support\Carbon::parse($delivery->payload['occurred_at'])->timezone(config('app.timezone'))->format('M j, Y g:i A T') }}. Open FarSell for the latest status.</p>
        <p style="margin:24px 0;"><a href="{{ url($delivery->payload['action_path']) }}" style="display:inline-block;padding:12px 18px;background:#7c3aed;color:#fff;text-decoration:none;border-radius:8px;">{{ $delivery->payload['action_label'] }}</a></p>
    </main>
</body>
</html>
