<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = str_replace('\\', '/', config('database.connections.sqlite.database'));
if (config('database.default') !== 'sqlite' || ! str_contains($database, '/storage/app/security-check/')) {
    throw new RuntimeException('Refusing to inspect a non-evidence database.');
}
$user = DB::table('users')->where('email', 'topic9.buyer@example.test')->first(['name', 'email', 'password', 'privacy_notice_version']);
echo json_encode([
    'source' => 'Direct SQLite SELECT from isolated dummy database',
    'query' => "SELECT name, email, password, privacy_notice_version FROM users WHERE email = 'topic9.buyer@example.test'",
    'row' => $user,
    'password_algorithm' => password_get_info($user->password)['algoName'],
    'plaintext_password_stored' => $user->password === 'Demo-Only-12345',
    'password_verification_succeeds' => Hash::check('Demo-Only-12345', $user->password),
    'weak_signup_account_exists' => DB::table('users')->where('email', 'topic9.weak@example.test')->exists(),
    'no_consent_account_exists' => DB::table('users')->where('email', 'topic9.noconsent@example.test')->exists(),
    'deletion_requests' => DB::table('account_deletion_requests')->get(['user_id', 'status', 'response', 'reviewed_at']),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
