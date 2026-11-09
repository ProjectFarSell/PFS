<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Security\AccountSecurity;
use App\Services\Security\AuditTrail;
use App\Support\DemoAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class DemoSellerAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo seller repair is only available in local/testing environments.');
        }
        DB::transaction(function () {
            $seller = User::where('email', 'seller@farsell.test')->lockForUpdate()->first();
            if ($seller?->suspended_at || ($seller && ! in_array($seller->role, [UserRole::Buyer, UserRole::Seller], true))) {
                throw new RuntimeException('Reserved demo seller is suspended or has an unexpected role; no account was changed.');
            }
            if ($seller?->role === UserRole::Buyer) {
                $this->assertUnlinked($seller);
                Password::deleteToken($seller);
                app(AccountSecurity::class)->deleteSessions($seller);
                DB::table('verification_challenges')->where('user_id', $seller->id)->delete();
                DB::table('sign_in_challenges')->where('user_id', $seller->id)->delete();
                $seller->delete();
                $seller = null;
            }
            $seller ??= User::create(['email' => 'seller@farsell.test', 'name' => 'Demo Seller', 'role' => UserRole::Seller, 'password' => 'password']);
            DemoAccount::mark($seller);
            $seller->shop()->firstOrCreate(['user_id' => $seller->id], [
                'name' => 'Demo Seller Shop', 'slug' => 'demo-seller-'.Str::uuid(),
                'tagline' => 'Local demo seller workspace', 'city' => 'Quezon City', 'is_active' => true,
            ]);
            app(AuditTrail::class)->record('demo_seller.setup', $seller);
        }, 3);
        $this->command?->info('Demo seller is ready. Existing seller credentials and shop ownership were preserved. New accounts use password: password');
    }

    private function assertUnlinked(User $user): void
    {
        foreach (Schema::getTables() as $table) {
            // These are disposable verification records; no business/history records are deleted.
            if (in_array($table['name'], ['verification_challenges', 'sign_in_challenges'], true)) {
                continue;
            }
            foreach (Schema::getForeignKeys($table['name']) as $foreign) {
                if ($foreign['foreign_table'] === 'users' && count($foreign['columns']) === 1
                    && DB::table($table['name'])->where($foreign['columns'][0], $user->id)->exists()) {
                    throw new RuntimeException('Reserved buyer has linked records in '.$table['name'].'; no account was removed. Review it manually.');
                }
            }
        }
    }
}
