<?php

return [
    'require_heartbeats' => (bool) env('REQUIRE_OPERATIONAL_HEARTBEATS', env('APP_ENV', 'production') === 'production'),
    'backup_password' => env('BACKUP_PASSWORD'),
];
