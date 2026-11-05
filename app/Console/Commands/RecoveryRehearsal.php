<?php

namespace App\Console\Commands;

use App\Services\Operations\DatabaseRecovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class RecoveryRehearsal extends Command
{
    protected $signature = 'ops:rehearse';

    protected $description = 'Rehearse migration rollback/reapply and encrypted SQLite recovery using a new dummy database only';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Run this dummy-data rehearsal in local/testing only. Production recovery must use the hosting-specific runbook.');

            return self::FAILURE;
        }
        $original = config('database.default');
        $cache = config('cache.default');
        $password = config('operations.backup_password');
        $directory = storage_path('app/private/ops-work/rehearsal-'.Str::uuid());
        File::ensureDirectoryExists($directory, 0700);
        $database = $directory.'/dummy.sqlite';
        touch($database);
        $backup = null;
        try {
            config(['database.connections.rehearsal' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => true], 'cache.default' => 'array', 'operations.backup_password' => Str::random(48)]);
            DB::setDefaultConnection('rehearsal');
            Artisan::call('migrate', ['--database' => 'rehearsal', '--force' => true]);
            DB::table('users')->insert(['name' => 'Recovery Dummy', 'email' => 'recovery@example.test', 'password' => Hash::make('Dummy-Only-123'), 'role' => 'buyer', 'created_at' => now(), 'updated_at' => now()]);
            $before = DB::table('users')->value('password');
            Artisan::call('migrate:rollback', ['--database' => 'rehearsal', '--step' => 1, '--force' => true]);
            Artisan::call('migrate', ['--database' => 'rehearsal', '--force' => true]);
            if (DB::table('users')->value('password') !== $before) {
                throw new \RuntimeException('Dummy account changed during rollback/reapply.');
            }
            $service = app(DatabaseRecovery::class);
            $backup = $service->create();
            $verified = $service->verify($backup);
            $this->line(json_encode(['migration_rollback_reapply' => 'passed', 'dummy_account_preserved' => true, 'restore' => $verified, 'live_database_modified' => false], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Rehearsal failed: '.$exception::class.'. The live database was not selected.');

            return self::FAILURE;
        } finally {
            DB::purge('rehearsal');
            DB::setDefaultConnection($original);
            config(['cache.default' => $cache, 'operations.backup_password' => $password]);
            if ($backup) {
                File::delete(app(DatabaseRecovery::class)->directory().DIRECTORY_SEPARATOR.$backup);
            }
            $parent = realpath(storage_path('app/private/ops-work'));
            $resolved = realpath($directory);
            if ($parent && $resolved && str_starts_with($resolved, $parent.DIRECTORY_SEPARATOR)) {
                File::deleteDirectory($resolved);
            }
        }
    }
}
