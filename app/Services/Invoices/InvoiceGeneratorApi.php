<?php

namespace App\Services\Invoices;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class InvoiceGeneratorApi
{
    public function generate(Order $order): string
    {
        $apiKey = config('services.invoice_generator.api_key');
        if (blank($apiKey)) {
            throw new RuntimeException('Invoice Generator API key is not configured.');
        }

        $order->loadMissing('items');
        $payload = $this->payload($order);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $path = "invoices/{$order->id}/{$hash}.pdf";
        $disk = Storage::disk('local');

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        $apiResponse = Http::withToken($apiKey)
            ->accept('application/pdf')
            ->asJson()
            ->timeout(30)
            ->post(config('services.invoice_generator.endpoint'), $payload);

        if (! $apiResponse->successful()) {
            throw new RuntimeException('Invoice Generator API returned HTTP '.$apiResponse->status().'.');
        }

        $pdf = $apiResponse->body();
        if (! str_starts_with($pdf, '%PDF-')) {
            throw new RuntimeException('Invoice Generator API did not return a PDF.');
        }

        $disk->put($path, $pdf);

        return $pdf;
    }

    private function payload(Order $order): array
    {
        return [
            'from' => config('services.invoice_generator.from'),
            'to' => trim(($order->guest_name ?: 'Buyer')."\n".$order->ship_to),
            'ship_to' => $order->ship_to,
            'number' => $order->number,
            'date' => $order->created_at->format('M j, Y'),
            'currency' => 'php',
            'theme' => 'classic',
            'fields' => [
                'tax' => false,
                'discounts' => false,
                'shipping' => true,
            ],
            'shipping' => (float) $order->shipping_fee,
            'items' => $order->items->map(function ($item): array {
                $options = collect($item->variant_options ?? [])
                    ->map(fn ($value, $name) => $name.': '.$value)
                    ->join(', ');
                $descriptionParts = array_filter([
                    $options,
                    'SKU: '.($item->product_sku ?: 'Unavailable'),
                ]);

                return [
                    'name' => $item->name,
                    'description' => implode(' · ', $descriptionParts),
                    'quantity' => (int) $item->qty,
                    'unit_cost' => (float) $item->unit_price,
                ];
            })->all(),
        ];
    }
}
