<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use ZipArchive;

class DatabaseRecovery
{
    public function directory(): string
    {
        return storage_path('app/private/ops-backups');
    }

    private function password(): string
    {
        $password = (string) config('operations.backup_password');
        if (strlen($password) < 20 || ! ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256, true)) {
            throw new RuntimeException('A BACKUP_PASSWORD of at least 20 characters and ZIP AES-256 support are required.');
        }

        return $password;
    }

    private function work(): string
    {
        $path = storage_path('app/private/ops-work/'.Str::uuid());
        File::ensureDirectoryExists($path, 0700);

        return $path;
    }

    private function cleanup(string $path): void
    {
        $root = realpath(storage_path('app/private/ops-work'));
        $resolved = realpath($path);
        if ($root && $resolved && str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
            File::deleteDirectory($resolved);
        }
    }

    private function counts(PDO $pdo): array
    {
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM "'.str_replace('"', '""', $table).'"')->fetchColumn();
        }

        return $counts;
    }

    public function create(bool $includeFiles = false): string
    {
        $password = $this->password();
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Use the documented MySQL/provider snapshot procedure for this database; the built-in encrypted backup command supports SQLite.');
        }
        if (DB::connection()->transactionLevel() > 0) {
            throw new RuntimeException('A consistent snapshot must run outside a database transaction.');
        }
        File::ensureDirectoryExists($this->directory(), 0700);
        $work = $this->work();
        $name = 'ops-'.now()->format('Ymd-His').'-'.Str::uuid().'.zip';
        $archive = $this->directory().DIRECTORY_SEPARATOR.$name;
        $zip = new ZipArchive;
        try {
            $snapshot = $work.DIRECTORY_SEPARATOR.'database.sqlite';
            DB::connection()->getPdo()->exec('VACUUM INTO '.DB::connection()->getPdo()->quote($snapshot));
            @chmod($snapshot, 0600);
            $pdo = new PDO('sqlite:'.$snapshot);
            $counts = $this->counts($pdo);
            $pdo = null;
            if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('Cannot create the encrypted backup archive.');
            }
            $zip->setPassword($password);
            $hashes = [];
            $add = function (string $path, string $entry) use ($zip, &$hashes): void {
                if (! $zip->addFile($path, $entry) || ! $zip->setEncryptionName($entry, ZipArchive::EM_AES_256)) {
                    throw new RuntimeException('Cannot encrypt a backup entry.');
                }
                $hashes[$entry] = hash_file('sha256', $path);
            };
            $add($snapshot, 'database.sqlite');
            if ($includeFiles) {
                $roots = [
                    'private/rider-documents' => config('filesystems.disks.local.root').'/rider-documents',
                    'private/delivery-proof' => config('filesystems.disks.local.root').'/delivery-proof',
                    'private/return-evidence' => config('filesystems.disks.local.root').'/return-evidence',
                    'private/product-review-media' => config('filesystems.disks.local.root').'/product-review-media',
                    'public' => config('filesystems.disks.public.root'),
                ];
                foreach ($roots as $prefix => $root) {
                    if (! is_dir($root)) {
                        continue;
                    }
                    foreach (File::allFiles($root) as $file) {
                        if ($file->isLink() || str_starts_with($file->getFilename(), '.')) {
                            continue;
                        }
                        $relative = str_replace('\\', '/', $file->getRelativePathname());
                        $add($file->getPathname(), 'uploads/'.$prefix.'/'.$relative);
                    }
                }
            }
            $manifest = ['created_at' => now()->toIso8601String(), 'release' => config('release.version'), 'engine' => 'sqlite', 'local_files_included' => $includeFiles, 'cloud_storage_included' => false, 'counts' => $counts, 'sha256' => $hashes];
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            if (! $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256) || ! $zip->close()) {
                throw new RuntimeException('Cannot finalize the encrypted backup.');
            }
            @chmod($archive, 0600);

            return $name;
        } finally {
            unset($zip);
            $this->cleanup($work);
        }
    }

    public function verify(string $name): array
    {
        if (! preg_match('/^ops-[A-Za-z0-9-]+\.zip$/', $name)) {
            throw new RuntimeException('Use an ops backup filename from the private backup directory.');
        }
        $zip = new ZipArchive;
        if ($zip->open($this->directory().DIRECTORY_SEPARATOR.$name, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Cannot open backup archive.');
        }
        $zip->setPassword($this->password());
        $work = $this->work();
        try {
            $raw = $zip->getFromName('manifest.json');
            if ($raw === false) {
                throw new RuntimeException('Wrong backup password or damaged manifest.');
            }
            $manifest = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (($manifest['engine'] ?? null) !== 'sqlite' || ! isset($manifest['sha256']['database.sqlite'], $manifest['counts'])) {
                throw new RuntimeException('Unsupported backup manifest.');
            }
            foreach ($manifest['sha256'] as $entry => $hash) {
                $stream = $zip->getStream($entry);
                if (! $stream) {
                    throw new RuntimeException('Missing encrypted backup entry.');
                }
                $context = hash_init('sha256');
                hash_update_stream($context, $stream);
                fclose($stream);
                if (! hash_equals($hash, hash_final($context))) {
                    throw new RuntimeException('Backup checksum mismatch.');
                }
            }
            $target = $work.DIRECTORY_SEPARATOR.'restored.sqlite';
            $stream = $zip->getStream('database.sqlite');
            $file = fopen($target, 'xb');
            stream_copy_to_stream($stream, $file);
            fclose($file);
            fclose($stream);
            @chmod($target, 0600);
            $pdo = new PDO('sqlite:'.$target);
            $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            $counts = $this->counts($pdo);
            $pdo = null;
            if ($integrity !== 'ok' || $counts !== $manifest['counts']) {
                throw new RuntimeException('Restored database integrity or row counts differ.');
            }
            Cache::put('ops.backup.verified_at', now()->timestamp, 172800);

            return ['integrity' => 'ok', 'tables_verified' => count($counts), 'entries_verified' => count($manifest['sha256']), 'local_files_included' => $manifest['local_files_included'], 'cloud_storage_included' => false];
        } finally {
            $zip->close();
            $this->cleanup($work);
        }
    }
}
