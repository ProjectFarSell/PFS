<?php

namespace App\Services\Operations;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReleaseReadiness
{
    public function checks(bool $boot = false): array
    {
        $checks = [];
        $add = function (string $name, bool $pass, string $action) use (&$checks): void {
            $checks[] = compact('name', 'pass', 'action');
        };
        $add('deployment_environment', app()->environment('production', 'staging'), 'Set the correct production/staging environment; keep local environments separate.');
        $add('debug_disabled', config('app.debug') === false, 'Disable APP_DEBUG.');
        $add('admin_security_gates', config('security.admin_mfa_required') && config('security.sensitive_confirmation_required'), 'Require admin MFA and recent identity confirmation before administrative writes.');
        $add('role_sign_in_gates', config('security.seller_mfa_required') && config('security.login_otp_required'), 'Require seller authenticator and buyer/rider sign-in OTP verification.');
        $key = (string) config('app.key');
        $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $add('stable_application_key', is_string($decoded) && Encrypter::supported($decoded, config('app.cipher')), 'Provision and preserve APP_KEY; keep an offline protected copy for encrypted delivery PINs.');
        $url = config('app.url');
        $host = (string) parse_url($url, PHP_URL_HOST);
        $add('https_url', parse_url($url, PHP_URL_SCHEME) === 'https' && $host !== '' && ! in_array($host, ['localhost', '127.0.0.1', 'example.com']) && ! preg_match('/\.(invalid|example|test|localhost)$/i', $host), 'Set a real HTTPS deployment URL and verify its certificate in the browser.');
        $add('secure_session_cookies', config('session.secure') === true && config('session.http_only') === true && in_array(config('session.same_site'), ['lax', 'strict'], true), 'Use Secure, HttpOnly session cookies with SameSite Lax/Strict.');
        $proxies = config('security.trusted_proxies', []);
        $add('explicit_proxy_trust', ! array_intersect($proxies, ['*', '**', '0.0.0.0/0', '::/0', 'REMOTE_ADDR']), 'Trust only documented load-balancer addresses; leave empty for a direct HTTPS origin.');
        $add('real_payment_mode', ! config('release.allow_demo_payments'), 'Disable the demo payment gateway for staging/production; COD is supported.');
        if ($boot) {
            return $checks;
        }

        $add('release_revision', (bool) preg_match('/^[a-f0-9]{7,40}$/i', (string) config('release.version')), 'Set RELEASE_VERSION to the deployed Git commit SHA.');
        $add('persistent_storage_drivers', ! in_array(config('session.driver'), ['array', 'cookie']) && ! in_array(config('cache.default'), ['array', 'null']), 'Use persistent sessions/cache and a shared store when running multiple instances.');
        $add('background_notifications', config('notifications.enabled') && config('notifications.connection') === 'database' && config('operations.require_heartbeats'), 'Use the database notification queue and require scheduler/worker heartbeats for this release.');
        $add('delivery_mail_transport', ! in_array(config('notifications.mailer'), ['log', 'array', 'mailpit']), 'Configure and verify a real notification mail transport before inviting buyers.');
        $add('beta_area_configured', ! config('release.beta_enabled') || count(config('release.beta_city_ids', [])) > 0, 'Choose supported PSGC city IDs before enabling the regional beta.');
        $add('backup_encryption_configured', strlen((string) config('operations.backup_password')) >= 20, 'Provision a strong BACKUP_PASSWORD separately and keep it with the protected recovery keys.');
        $add('frontend_build', is_file(public_path('build/manifest.json')) && ! is_file(public_path('hot')), 'Build production assets and remove the Vite hot-file artifact.');
        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $ran = $migrator->getRepository()->getRan();
            $add('migrations_current', count(array_diff(array_keys($files), $ran)) === 0, 'Back up first, then apply pending migrations.');
            $add('no_demo_accounts', ! DB::table('users')->where('is_demo_account', true)->orWhere('email', 'like', '%.test')->exists(), 'Use a clean production database and provision real accounts; do not delete historical orders to clear this check.');
            $add('no_demo_payments', ! DB::table('orders')->where('payment_method', 'gateway_stub')->exists(), 'Keep demo orders separate from production.');
            $status = app(OperationalStatus::class)->inspect();
            $add('runtime_health', $status['healthy'], 'Check database, scheduler, notification worker, failed jobs, and stale notifications with ops:status.');
            $verified = Cache::get('ops.backup.verified_at');
            $add('recent_restore_check', is_numeric($verified) && $verified >= now()->subHours(config('release.backup_max_age_hours'))->timestamp, 'Create an encrypted backup and perform an isolated restore check; separately verify provider/off-site recovery.');
        } catch (Throwable) {
            $add('database_checks', false, 'Database or operational checks could not complete. No secret connection details are printed.');
        }

        return $checks;
    }
}
