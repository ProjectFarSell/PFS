<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;

// Development-only evidence server; use built assets even if another Vite dev server is running.
$public = realpath(__DIR__.'/../../public');
$path = realpath($public.rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/'));
if ($path && str_starts_with($path, $public.DIRECTORY_SEPARATOR) && is_file($path)) {
    return false;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$app->make(Vite::class)->useHotFile(storage_path('app/security-check/no-hot-file'));
$app->handleRequest(Request::capture());
