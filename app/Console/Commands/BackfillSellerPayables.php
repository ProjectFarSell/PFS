<?php

namespace App\Console\Commands;

use App\Models\SettlementAdjustment;
use App\Models\ShipmentSettlement;
use App\Services\Orders\FinancialLocks;
use App\Services\Orders\FinancialTransaction;
use App\Services\Orders\SellerPayableLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillSellerPayables extends Command
{
    protected $signature = 'payouts:backfill {--dry-run : Report source rows without writing anything}';

    protected $description = 'Import identifiable historical settlements and refund adjustments into the immutable payable ledger';

    public function handle(): int
    {
        $counts = ['eligible' => 0, 'already_imported' => 0, 'unidentifiable' => 0, 'imported' => 0];
        foreach ([ShipmentSettlement::class => 'shipment_settlement_id', SettlementAdjustment::class => 'settlement_adjustment_id'] as $class => $source) {
            $class::query()->orderBy('id')->chunkById(200, function ($rows) use ($source, &$counts) {
                foreach ($rows as $row) {
                    $settlement = $row instanceof ShipmentSettlement ? $row : ShipmentSettlement::find($row->shipment_settlement_id);
                    $part = $settlement?->fulfillment;
                    if (! $part || ! $part->shop_id || ! DB::table('shops')->where('id', $part->shop_id)->exists() || ! DB::table('orders')->where('id', $part->order_id)->exists()) {
                        $counts['unidentifiable']++;

                        continue;
                    }
                    if (DB::table('seller_payable_entries')->where($source, $row->id)->exists()) {
                        $counts['already_imported']++;

                        continue;
                    }
                    $counts['eligible']++;
                    if ($this->option('dry-run')) {
                        continue;
                    }
                    app(FinancialTransaction::class)->run(function () use ($row, $source, $part, &$counts) {
                        app(FinancialLocks::class)->order($part->order_id);
                        app(FinancialLocks::class)->shop($part->shop_id);
                        if (DB::table('seller_payable_entries')->where($source, $row->id)->exists()) {
                            return;
                        }
                        $ledger = app(SellerPayableLedger::class);
                        $row instanceof ShipmentSettlement ? $ledger->postSettlement($row) : $ledger->postAdjustment($row);
                        $counts['imported']++;
                    });
                }
            });
        }
        $this->line(json_encode(['dry_run' => (bool) $this->option('dry-run'), ...$counts], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
