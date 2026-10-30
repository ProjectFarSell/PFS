<?php

return [
    'enabled' => (bool) env('ORDER_NOTIFICATIONS_ENABLED', true),
    'connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'database'),
    'queue' => env('NOTIFICATION_QUEUE', 'notifications'),
    // Default to a local log until a sandbox or delivery provider is selected.
    'mailer' => env('NOTIFICATION_MAILER', 'log'),
];
