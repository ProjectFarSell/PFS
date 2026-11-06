<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\FulfillmentEvent;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'export' => ['nullable', 'in:csv'],
        ]);
        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        // A max-only date filter starts at the beginning of that selected month.
        if (! $request->filled('from') && $to < $from) {
            $from = CarbonImmutable::parse($to)->startOfMonth()->toDateString();
        }
        if ($from > $to) {
            return back()->withErrors(['to' => 'End date must be on or after the start date.']);
        }
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->endOfDay();
        $shop = $request->user()->shop()->firstOrFail();
        $completion = FulfillmentEvent::query()->selectRaw('fulfillment_id, MAX(created_at) as completed_at')->where('status', 'completed')->groupBy('fulfillment_id');
        $base = OrderItem::query()->join('fulfillments', 'fulfillments.id', '=', 'order_items.fulfillment_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoinSub($completion, 'completion', 'completion.fulfillment_id', '=', 'fulfillments.id')
            ->where('fulfillments.shop_id', $shop->id)->where('fulfillments.status', 'completed')
            ->whereBetween(DB::raw('COALESCE(completion.completed_at, fulfillments.updated_at)'), [$start, $end]);
        $sales = (clone $base)->where('orders.payment_method', 'cod');
        $refunds = ReturnRequest::query()->join('order_items', 'order_items.id', '=', 'return_requests.order_item_id')
            ->join('fulfillments', 'fulfillments.id', '=', 'order_items.fulfillment_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('fulfillments.shop_id', $shop->id)->where('return_requests.status', 'refunded')
            ->whereBetween('return_requests.refunded_at', [$start, $end]);
        if (($data['export'] ?? null) === 'csv') {
            return response()->streamDownload(function () use ($sales, $refunds) {
                $out = fopen('php://output', 'w');
                $write = function (array $row) use ($out) {
                    $row = array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value, $row);
                    fputcsv($out, $row, ',', '"', '');
                };
                $write(['Type', 'Date', 'Order', 'Item', 'SKU', 'Quantity', 'Merchandise amount (PHP)']);
                foreach ((clone $sales)->select('order_items.*', 'orders.number')->selectRaw('COALESCE(completion.completed_at, fulfillments.updated_at) as sale_date')->orderBy('order_items.id')->cursor() as $row) {
                    $write(['Completed COD sale', $row->sale_date, $row->number, $row->name, $row->product_sku, $row->qty, $row->line_total]);
                }
                foreach ((clone $refunds)->select('return_requests.*', 'orders.number', 'order_items.name', 'order_items.product_sku')->orderBy('return_requests.id')->cursor() as $row) {
                    $write(['Recorded reimbursement', $row->refunded_at, $row->number, $row->name, $row->product_sku, $row->quantity, -round((float) $row->approved_amount, 2)]);
                }
                fclose($out);
            }, 'farsell-sales-'.$from.'-to-'.$to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }
        $gross = (clone $sales)->sum('order_items.line_total');
        $reimbursed = (clone $refunds)->sum('return_requests.approved_amount');
        $stats = [
            'sales' => $gross,
            'units' => (clone $sales)->sum('order_items.qty'),
            'shipments' => (clone $sales)->distinct()->count('fulfillments.id'),
            'refunds' => $reimbursed,
            'net' => round((float) $gross - (float) $reimbursed, 2),
            'demo' => (clone $base)->where('orders.payment_method', '!=', 'cod')->sum('order_items.line_total'),
            'pending' => $shop->fulfillments()->whereNotIn('status', ['completed', 'rejected', 'cancelled', 'returned'])->whereBetween('created_at', [$start, $end])->count(),
            'cancelled' => $shop->fulfillments()->whereIn('status', ['rejected', 'cancelled', 'returned'])->whereBetween('created_at', [$start, $end])->count(),
        ];
        $bestSellers = (clone $sales)->select('order_items.product_id', 'order_items.name', 'order_items.product_sku')
            ->selectRaw('SUM(order_items.qty) as units, SUM(order_items.line_total) as merchandise_sales')
            ->groupBy('order_items.product_id', 'order_items.name', 'order_items.product_sku')->orderByDesc('units')->orderBy('order_items.name')->limit(10)->get();

        return view('seller.sales', compact('shop', 'from', 'to', 'stats', 'bestSellers'));
    }
}
