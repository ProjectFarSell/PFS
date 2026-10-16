<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class BackupProductImages extends Command
{
    protected $signature = 'products:backup-images {--zip : Also export a compressed zip archive for Google Drive}';

    protected $description = 'Backs up local product images from public storage into the tracked database/data/product-images repository folder';

    public function handle(): int
    {
        $storageDir = storage_path('app/public/products');
        $repoDir = database_path('data/product-images');

        if (! File::isDirectory($storageDir)) {
            $this->warn("No storage directory found at {$storageDir}. Nothing to backup.");

            return Command::SUCCESS;
        }

        if (! File::isDirectory($repoDir)) {
            File::makeDirectory($repoDir, 0755, true);
        }

        $files = array_filter(File::files($storageDir), fn ($f) => ! str_starts_with($f->getFilename(), '.'));
        $newBackupCount = 0;
        foreach ($files as $file) {
            $dest = $repoDir.DIRECTORY_SEPARATOR.$file->getFilename();
            if (! File::exists($dest)) {
                File::copy($file->getPathname(), $dest);
                $newBackupCount++;
                $this->line('Backed up new image: '.$file->getFilename());
            }
        }

        $totalInRepo = count(File::files($repoDir));
        $this->info("Backup complete! {$newBackupCount} new image(s) backed up. Total images in repo: {$totalInRepo}.");

        if ($this->option('zip')) {
            $backupDir = storage_path('app/backups');
            if (! File::isDirectory($backupDir)) {
                File::makeDirectory($backupDir, 0755, true);
            }

            $zipName = 'product-images-backup-'.now()->format('Y-m-d-His').'.zip';
            $zipPath = $backupDir.DIRECTORY_SEPARATOR.$zipName;

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach (File::files($repoDir) as $file) {
                    $zip->addFile($file->getPathname(), $file->getFilename());
                }
                $zip->close();
                $this->info("Created zip archive at: {$zipPath} (ready for Google Drive)");
            } else {
                $this->error('Failed to create zip archive.');
            }
        }

        return Command::SUCCESS;
    }
}
