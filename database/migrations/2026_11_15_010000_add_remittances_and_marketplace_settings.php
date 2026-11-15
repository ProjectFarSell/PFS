<?php

use App\Support\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('commission_bps');
            $table->unsignedInteger('delivery_fee_cents');
            $table->unsignedTinyInteger('payout_hold_days')->default(7);
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        $checks = 'id = 1 AND commission_bps BETWEEN 0 AND 10000 AND delivery_fee_cents BETWEEN 0 AND 100000 AND payout_hold_days BETWEEN 0 AND 90 AND version > 0';
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE marketplace_settings ADD CONSTRAINT marketplace_settings_bounds CHECK ('.$checks.')');
        } else {
            DB::statement("CREATE TRIGGER marketplace_settings_insert_bounds BEFORE INSERT ON marketplace_settings WHEN NOT ({$this->sqliteSettingsCheck()}) BEGIN SELECT RAISE(ABORT, 'Invalid marketplace settings'); END");
            DB::statement("CREATE TRIGGER marketplace_settings_update_bounds BEFORE UPDATE ON marketplace_settings WHEN NOT ({$this->sqliteSettingsCheck()}) BEGIN SELECT RAISE(ABORT, 'Invalid marketplace settings'); END");
        }
        DB::table('marketplace_settings')->insert(['id' => 1, 'commission_bps' => (int) config('marketplace.commission_bps', 800),
            'delivery_fee_cents' => Money::cents((string) config('commerce.delivery_fee', '49.00')), 'payout_hold_days' => 7, 'version' => 1,
            'created_at' => now(), 'updated_at' => now()]);
        Schema::table('orders', fn (Blueprint $table) => $table->json('fee_policy')->nullable());
        Schema::create('rider_cod_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_profile_id')->constrained('rider_profiles')->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->timestamp('received_at');
            $table->text('note');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['rider_profile_id', 'received_at']);
            $table->index(['rider_profile_id', 'voided_at']);
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE rider_cod_remittances ADD CONSTRAINT remittance_positive CHECK (amount_cents > 0), ADD CONSTRAINT remittance_void_state CHECK ((voided_at IS NULL AND voided_by IS NULL AND void_reason IS NULL) OR (voided_at IS NOT NULL AND voided_by IS NOT NULL AND void_reason IS NOT NULL AND CHAR_LENGTH(TRIM(void_reason)) > 0))');
            DB::unprepared("CREATE TRIGGER rider_remittance_no_edit BEFORE UPDATE ON rider_cod_remittances FOR EACH ROW BEGIN IF OLD.voided_at IS NOT NULL OR NOT (NEW.id <=> OLD.id) OR NOT (NEW.rider_profile_id <=> OLD.rider_profile_id) OR NOT (NEW.amount_cents <=> OLD.amount_cents) OR NOT (NEW.received_at <=> OLD.received_at) OR NOT (NEW.note <=> OLD.note) OR NOT (NEW.recorded_by <=> OLD.recorded_by) OR NOT (NEW.created_at <=> OLD.created_at) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Remittances cannot be edited'; END IF; END");
            DB::statement("CREATE TRIGGER rider_remittance_no_delete BEFORE DELETE ON rider_cod_remittances FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Remittances must be retained'");
        } else {
            DB::statement("CREATE TRIGGER rider_remittance_insert_bounds BEFORE INSERT ON rider_cod_remittances WHEN NEW.amount_cents <= 0 OR NEW.voided_at IS NOT NULL OR NEW.voided_by IS NOT NULL OR NEW.void_reason IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Invalid remittance'); END");
            DB::statement("CREATE TRIGGER rider_remittance_no_edit BEFORE UPDATE ON rider_cod_remittances WHEN OLD.voided_at IS NOT NULL OR NEW.id IS NOT OLD.id OR NEW.rider_profile_id IS NOT OLD.rider_profile_id OR NEW.amount_cents IS NOT OLD.amount_cents OR NEW.received_at IS NOT OLD.received_at OR NEW.note IS NOT OLD.note OR NEW.recorded_by IS NOT OLD.recorded_by OR NEW.created_at IS NOT OLD.created_at OR NEW.voided_at IS NULL OR NEW.voided_by IS NULL OR NEW.void_reason IS NULL OR length(trim(NEW.void_reason)) = 0 BEGIN SELECT RAISE(ABORT, 'Remittances cannot be edited'); END");
            DB::statement("CREATE TRIGGER rider_remittance_no_delete BEFORE DELETE ON rider_cod_remittances BEGIN SELECT RAISE(ABORT, 'Remittances must be retained'); END");
        }
    }

    private function sqliteSettingsCheck(): string
    {
        return 'NEW.id = 1 AND NEW.commission_bps BETWEEN 0 AND 10000 AND NEW.delivery_fee_cents BETWEEN 0 AND 100000 AND NEW.payout_hold_days BETWEEN 0 AND 90 AND NEW.version > 0';
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_cod_remittances');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('fee_policy'));
        Schema::dropIfExists('marketplace_settings');
    }
};
