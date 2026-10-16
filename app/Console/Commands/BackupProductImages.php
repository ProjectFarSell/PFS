<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupProductImages extends Command
{
    protected $signature = 'products:backup-images {--zip : Also export a compressed zip archive}';

    protected $description = 'Downloads product images from Cloudflare R2 into the tracked database/data/product-images repository folder';

    public function handle(): int
    {
        $repoDir = database_path('data/product-images');

        if (! File::isDirectory($repoDir)) {
            File::makeDirectory($repoDir, 0755, true);
        }

        $r2Files = collect(Storage::disk('r2')->files('products'))
            ->filter(fn ($f) => ! str_starts_with(basename($f), '.'));

        if ($r2Files->isEmpty()) {
            $this->warn('No images found in Cloudflare R2 bucket. Nothing to back up.');

            return Command::SUCCESS;
        }

        $newBackupCount = 0;
        foreach ($r2Files as $r2Path) {
            $filename = basename($r2Path);
            $dest = $repoDir . DIRECTORY_SEPARATOR . $filename;

            if (! File::exists($dest)) {
                File::put($dest, Storage::disk('r2')->get($r2Path));
                $newBackupCount++;
                $this->line("Backed up: {$filename}");
            }
        }

        $totalInRepo = count(array_filter(File::files($repoDir), fn ($f) => ! str_starts_with($f->getFilename(), '.')));
        $this->info("Backup complete! {$newBackupCount} new image(s) backed up from R2. Total in repo: {$totalInRepo}.");

        if ($this->option('zip')) {
            $backupDir = storage_path('app/backups');
            if (! File::isDirectory($backupDir)) {
                File::makeDirectory($backupDir, 0755, true);
            }

            $zipName = 'product-images-backup-' . now()->format('Y-m-d-His') . '.zip';
            $zipPath = $backupDir . DIRECTORY_SEPARATOR . $zipName;

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach (File::files($repoDir) as $file) {
                    if (! str_starts_with($file->getFilename(), '.')) {
                        $zip->addFile($file->getPathname(), $file->getFilename());
                    }
                }
                $zip->close();
                $this->info("Created zip archive at: {$zipPath}");
            } else {
                $this->error('Failed to create zip archive.');
            }
        }

        return Command::SUCCESS;
    }
}
