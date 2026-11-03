<?php

return [
    // Set these to the addresses of the actual load balancer when hosting is chosen.
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
    'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('ALLOWED_HOSTS', ''))))),
    'hsts_seconds' => (int) env('HSTS_SECONDS', 31536000),
];
