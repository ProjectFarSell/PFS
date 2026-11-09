<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_demo_account')->default(false)->index());
        if (app()->environment(['local', 'testing'])) {
            // Reserved legacy seed identities only; never classify arbitrary .test addresses.
            foreach (['admin' => 'admin@farsell.test', 'buyer' => 'buyer@farsell.test', 'seller' => 'seller@farsell.test', 'rider' => 'rider@farsell.test'] as $role => $email) {
                DB::table('users')->where('email', $email)->where('role', $role)->update(['is_demo_account' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_demo_account']);
            $table->dropColumn('is_demo_account');
        });
    }
};
