<?php

return [
    'lalamove' => [
        'enabled' => (bool) env('LALAMOVE_ENABLED', false),
        'environment' => env('LALAMOVE_ENVIRONMENT', 'sandbox'),
        'allow_live_bookings' => (bool) env('LALAMOVE_ALLOW_LIVE_BOOKINGS', false),
        'key' => env('LALAMOVE_API_KEY'),
        'secret' => env('LALAMOVE_API_SECRET'),
    ],
];
