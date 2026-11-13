<?php

namespace App\Services\Mobile;

use RuntimeException;

class ApiFailure extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 409)
    {
        parent::__construct($message);
    }
}
