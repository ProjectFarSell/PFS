<?php
header('Content-Type: text/plain');

echo "=== 1. LARAVEL ERROR LOG (LAST 3000 CHARACTERS) ===\n";
$logPath = __DIR__ . '/../storage/logs/laravel.log';
if (file_exists($logPath)) {
    $content = file_get_contents($logPath);
    echo substr($content, -3000) ?: "Log file is empty.\n";
} else {
    echo "laravel.log does not exist yet.\n";
}

echo "\n\n=== 2. ENVIRONMENT & PERMISSION CHECKS ===\n";
echo ".env exists: " . (file_exists(__DIR__ . '/../.env') ? 'YES' : 'NO') . "\n";
echo "database.sqlite exists: " . (file_exists(__DIR__ . '/../database/database.sqlite') ? 'YES' : 'NO') . "\n";
echo "database/ directory writable: " . (is_writable(__DIR__ . '/../database') ? 'YES' : 'NO') . "\n";
echo "storage/ directory writable: " . (is_writable(__DIR__ . '/../storage') ? 'YES' : 'NO') . "\n";
echo "Vite manifest exists: " . (file_exists(__DIR__ . '/build/manifest.json') ? 'YES' : 'NO (Vite build missing)') . "\n";