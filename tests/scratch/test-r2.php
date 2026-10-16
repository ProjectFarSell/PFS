<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Storage;

try {
    config(['filesystems.disks.r2.throw' => true]);
    $result = Storage::disk('r2')->put('test.txt', 'hello r2');
    echo $result ? "Upload OK\n" : "Upload returned false\n";

    $exists = Storage::disk('r2')->exists('test.txt');
    echo $exists ? "File exists OK\n" : "File not found\n";

    Storage::disk('r2')->delete('test.txt');
    echo "Cleanup OK\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
