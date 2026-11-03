<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Support\PrivacyNotice;
use Illuminate\Contracts\Console\Kernel;

// Only use this script with the isolated evidence database created by capture.mjs.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = str_replace('\\', '/', config('database.connections.sqlite.database'));
if (config('database.default') !== 'sqlite' || ! str_contains($database, '/storage/app/security-check/')) {
    throw new RuntimeException('Refusing to seed outside the isolated security-check database.');
}
$buyer = User::create([
    'name' => 'Topic 9 Dummy Buyer', 'email' => 'topic9.buyer@example.test',
    'password' => 'Demo-Only-12345', 'role' => UserRole::Buyer,
]);
$buyer->forceFill(['privacy_accepted_at' => now(), 'privacy_notice_version' => PrivacyNotice::VERSION])->save();
User::create([
    'name' => 'Topic 9 Dummy Admin', 'email' => 'topic9.admin@example.test',
    'password' => 'Demo-Only-12345', 'role' => UserRole::Admin,
]);
$buyer->orders()->create([
    'number' => 'FS-TOPIC9-DEMO', 'status' => 'pending_payment', 'payment_method' => 'cod',
    'guest_email' => $buyer->email, 'guest_name' => $buyer->name,
    'ship_to' => 'DUMMY ADDRESS ONLY', 'subtotal' => 100, 'shipping_fee' => 49, 'total' => 149,
]);
echo "Isolated dummy buyer, admin, and order created.\n";
