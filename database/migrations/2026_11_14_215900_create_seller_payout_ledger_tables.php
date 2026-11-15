<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Seller payouts require the MySQL or SQLite database driver.');
        }

        $id = $driver === 'mysql' ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $foreignId = $driver === 'mysql' ? 'BIGINT UNSIGNED' : 'INTEGER';

        DB::statement("CREATE TABLE seller_payable_entries (
            id {$id}, shop_id {$foreignId} NOT NULL,
            amount_cents BIGINT NOT NULL,
            shipment_settlement_id {$foreignId} NULL,
            settlement_adjustment_id {$foreignId} NULL,
            created_at TIMESTAMP NULL,
            CONSTRAINT seller_payable_source_check CHECK ((shipment_settlement_id IS NULL) <> (settlement_adjustment_id IS NULL)),
            FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE RESTRICT,
            FOREIGN KEY (shipment_settlement_id) REFERENCES shipment_settlements(id) ON DELETE RESTRICT,
            FOREIGN KEY (settlement_adjustment_id) REFERENCES settlement_adjustments(id) ON DELETE RESTRICT
        )");
        DB::statement('CREATE UNIQUE INDEX seller_payable_settlement_unique ON seller_payable_entries (shipment_settlement_id)');
        DB::statement('CREATE UNIQUE INDEX seller_payable_adjustment_unique ON seller_payable_entries (settlement_adjustment_id)');
        DB::statement('CREATE INDEX seller_payable_shop_index ON seller_payable_entries (shop_id)');
        if ($driver === 'mysql') {
            DB::statement("CREATE TRIGGER seller_payable_no_update BEFORE UPDATE ON seller_payable_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payable entries are immutable'");
            DB::statement("CREATE TRIGGER seller_payable_no_delete BEFORE DELETE ON seller_payable_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payable entries are immutable'");
        } else {
            DB::statement("CREATE TRIGGER seller_payable_no_update BEFORE UPDATE ON seller_payable_entries BEGIN SELECT RAISE(ABORT, 'Payable entries are immutable'); END");
            DB::statement("CREATE TRIGGER seller_payable_no_delete BEFORE DELETE ON seller_payable_entries BEGIN SELECT RAISE(ABORT, 'Payable entries are immutable'); END");
        }

        Schema::create('seller_payouts', function (Blueprint $table) use ($driver): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['unpaid', 'paid', 'cancelled'])->default('unpaid')->index();
            $table->bigInteger('amount_cents');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'status']);
            if ($driver === 'mysql') {
                $table->unsignedBigInteger('unpaid_shop_id')->storedAs("CASE WHEN status = 'unpaid' THEN shop_id ELSE NULL END")->nullable()->unique('seller_payouts_one_unpaid');
            }
        });
        if ($driver === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX seller_payouts_one_unpaid ON seller_payouts (shop_id) WHERE status = 'unpaid'");
        }

        DB::statement("CREATE TABLE seller_payout_items (
            id {$id}, seller_payout_id {$foreignId} NOT NULL,
            seller_payable_entry_id {$foreignId} NOT NULL,
            active_entry_id {$foreignId} NULL, amount_cents BIGINT NOT NULL,
            CONSTRAINT seller_payout_active_entry_check CHECK (active_entry_id IS NULL OR active_entry_id = seller_payable_entry_id),
            FOREIGN KEY (seller_payout_id) REFERENCES seller_payouts(id) ON DELETE RESTRICT,
            FOREIGN KEY (seller_payable_entry_id) REFERENCES seller_payable_entries(id) ON DELETE RESTRICT,
            FOREIGN KEY (active_entry_id) REFERENCES seller_payable_entries(id) ON DELETE RESTRICT
        )");
        DB::statement('CREATE UNIQUE INDEX seller_payout_active_entry_unique ON seller_payout_items (active_entry_id)');
        DB::statement('CREATE INDEX seller_payout_items_payout_index ON seller_payout_items (seller_payout_id)');
        DB::statement('CREATE INDEX seller_payout_items_entry_index ON seller_payout_items (seller_payable_entry_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_payout_items');
        Schema::dropIfExists('seller_payouts');
        Schema::dropIfExists('seller_payable_entries');
    }
};
