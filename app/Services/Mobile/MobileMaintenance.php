<?php

namespace App\Services\Mobile;

use App\Models\Fulfillment;
use App\Models\MobileTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MobileMaintenance
{
    public function prune(): array
    {
        $tickets = MobileTicket::where('expires_at', '<', now()->subDay())->delete();
        $receipts = DB::table('mobile_receipts')->where('created_at', '<', now()->subDays(config('mobile.receipt_days')))->delete();
        $quotes = DB::table('mobile_quotes')->where('expires_at', '<', now()->subDays(config('mobile.receipt_days')))->delete();
        DB::table('mobile_events')->where('expires_at', '<', now()->subDays(30))->delete();
        $proofs = 0;
        // Direct multipart uploads are cleaned immediately on failure. Recover old
        // unreferenced files left by a terminated process, in a bounded daily batch.
        foreach (array_chunk(Storage::disk('local')->files('delivery-proof'), 200) as $paths) {
            $referenced = Fulfillment::whereIn('delivery_photo_path', $paths)->pluck('delivery_photo_path')->flip();
            foreach ($paths as $path) {
                if (! $referenced->has($path) && Storage::disk('local')->lastModified($path) < now()->subDay()->timestamp) {
                    $proofs += (int) Storage::disk('local')->delete($path);
                }
                if ($proofs >= 200) {
                    break 2;
                }
            }
        }
        $days = (int) config('mobile.proof_retention_days');
        if ($days > 0) {
            Fulfillment::whereIn('status', ['completed', 'returned', 'cancelled'])->whereNotNull('delivery_photo_path')->whereDoesntHave('items.returnRequest')->whereDoesntHave('items.replacementRequest')->whereHas('events', fn ($q) => $q->where('status', 'delivered')->where('created_at', '<', now()->subDays($days)))->limit(100)->get()->each(function ($part) use (&$proofs) {
                $path = $part->delivery_photo_path;
                if (str_starts_with($path, 'delivery-proof/') && Storage::disk('local')->delete($path)) {
                    $part->update(['delivery_photo_path' => null]);
                    $proofs++;
                }
            });
        }

        return compact('tickets', 'receipts', 'quotes', 'proofs');
    }
}
