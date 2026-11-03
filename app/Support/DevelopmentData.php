<?php

namespace App\Support;

use RuntimeException;

class DevelopmentData
{
    public static function assertAllowed(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demo accounts and catalog data must not be seeded in production.');
        }
    }
}
