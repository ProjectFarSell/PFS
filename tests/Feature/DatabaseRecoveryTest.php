<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Operations\DatabaseRecovery;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class DatabaseRecoveryTest extends TestCase
{
    use DatabaseMigrations;

    private array $backups = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['operations.backup_password' => 'Dummy-Backup-Password-Only-12345']);
    }

    protected function tearDown(): void
    {
        foreach ($this->backups as $name) {
            File::delete(app(DatabaseRecovery::class)->directory().DIRECTORY_SEPARATOR.$name);
        }
        parent::tearDown();
    }

    public function test_backup_is_encrypted_and_restore_check_does_not_replace_the_live_database(): void
    {
        $user = User::factory()->create();
        $service = app(DatabaseRecovery::class);
        $name = $this->backups[] = $service->create();
        $zip = new \ZipArchive;
        $zip->open($service->directory().DIRECTORY_SEPARATOR.$name, \ZipArchive::RDONLY);
        $this->assertFalse($zip->getFromName('manifest.json'));
        $zip->close();
        DB::table('users')->where('id', $user->id)->update(['name' => 'Changed after backup']);
        $report = $service->verify($name);
        $this->assertSame('ok', $report['integrity']);
        $this->assertGreaterThan(10, $report['tables_verified']);
        $this->assertSame('Changed after backup', DB::table('users')->where('id', $user->id)->value('name'));
        $this->assertNotNull(Cache::get('ops.backup.verified_at'));
    }

    public function test_wrong_backup_password_cannot_verify_or_claim_a_restore(): void
    {
        $service = app(DatabaseRecovery::class);
        $name = $this->backups[] = $service->create();
        config(['operations.backup_password' => 'Different-Dummy-Password-12345']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wrong backup password');
        $service->verify($name);
    }

    public function test_weak_backup_password_is_rejected(): void
    {
        config(['operations.backup_password' => 'weak']);
        $this->expectException(RuntimeException::class);
        app(DatabaseRecovery::class)->create();
    }

    public function test_optional_local_files_are_encrypted_and_verified_with_the_database(): void
    {
        $root = storage_path('app/private/ops-work/test-files-'.Str::uuid());
        File::ensureDirectoryExists($root.'/private/rider-documents', 0700);
        File::ensureDirectoryExists($root.'/public/avatars', 0700);
        File::put($root.'/private/rider-documents/dummy.txt', 'Dummy private document');
        File::put($root.'/public/avatars/dummy.txt', 'Dummy avatar');
        config(['filesystems.disks.local.root' => $root.'/private', 'filesystems.disks.public.root' => $root.'/public']);
        try {
            $service = app(DatabaseRecovery::class);
            $name = $this->backups[] = $service->create(true);
            $report = $service->verify($name);
            $this->assertTrue($report['local_files_included']);
            $this->assertFalse($report['cloud_storage_included']);
            $this->assertSame(3, $report['entries_verified']);
        } finally {
            $parent = realpath(storage_path('app/private/ops-work'));
            $resolved = realpath($root);
            if ($parent && $resolved && str_starts_with($resolved, $parent.DIRECTORY_SEPARATOR)) {
                File::deleteDirectory($resolved);
            }
        }
    }

    public function test_changed_archive_content_fails_the_checksum_check(): void
    {
        $service = app(DatabaseRecovery::class);
        $name = $this->backups[] = $service->create();
        $zip = new \ZipArchive;
        $zip->open($service->directory().DIRECTORY_SEPARATOR.$name);
        $zip->setPassword(config('operations.backup_password'));
        $zip->addFromString('database.sqlite', 'damaged database');
        $zip->setEncryptionName('database.sqlite', \ZipArchive::EM_AES_256);
        $zip->close();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checksum mismatch');
        $service->verify($name);
    }

    public function test_recovery_rehearsal_preserves_the_original_database(): void
    {
        $user = User::factory()->create(['name' => 'Keep original database']);
        $original = DB::getDefaultConnection();
        $this->artisan('ops:rehearse')->assertSuccessful();
        $this->assertSame($original, DB::getDefaultConnection());
        $this->assertSame('Keep original database', $user->fresh()->name);
        $this->assertDatabaseCount('users', 1);
    }
}
