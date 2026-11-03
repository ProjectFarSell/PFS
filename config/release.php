<?php

return [
    'version' => env('RELEASE_VERSION', 'development'),
    'checkout_enabled' => (bool) env('CHECKOUT_ENABLED', true),
    'allow_demo_payments' => (bool) env('ALLOW_DEMO_PAYMENTS', env('APP_ENV', 'production') !== 'production'),
    'beta_enabled' => (bool) env('BETA_ENABLED', false),
    'beta_city_ids' => array_values(array_filter(array_map('intval', explode(',', (string) env('BETA_CITY_IDS', ''))))),
    'slow_request_ms' => (int) env('SLOW_REQUEST_MS', 1000),
    'heartbeat_max_age' => (int) env('HEARTBEAT_MAX_AGE', 180),
    'queue_max_age' => (int) env('QUEUE_MAX_AGE', 300),
    'backup_max_age_hours' => (int) env('BACKUP_MAX_AGE_HOURS', 24),
];
