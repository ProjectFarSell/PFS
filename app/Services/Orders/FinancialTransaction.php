<?php

namespace App\Services\Orders;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use Throwable;

class FinancialTransaction
{
    public function run(callable $callback): mixed
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction($callback);
            } catch (Throwable $exception) {
                if ($attempt >= 3 || ! $this->isLockFailure($exception)) {
                    throw $exception;
                }
            }
        }
    }

    private function isLockFailure(Throwable $exception): bool
    {
        if (! $exception instanceof QueryException && ! $exception instanceof PDOException) {
            return false;
        }

        return in_array((int) ($exception->errorInfo[1] ?? 0), [1205, 1213, 5, 6], true);
    }
}
