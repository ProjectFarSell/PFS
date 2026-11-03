<?php

namespace App\Console\Commands;

use App\Services\Operations\ReleaseReadiness;
use Illuminate\Console\Command;

class ReleaseCheck extends Command
{
    protected $signature = 'release:check {--boot : Check startup configuration without requiring live workers} {--json}';

    protected $description = 'Check technical release gates without modifying records or publishing a release';

    public function handle(ReleaseReadiness $readiness): int
    {
        $checks = $readiness->checks((bool) $this->option('boot'));
        $pass = ! collect($checks)->contains('pass', false);
        if ($this->option('json')) {
            $this->line(json_encode(['ready' => $pass, 'checks' => $checks], JSON_PRETTY_PRINT));
        } else {
            $this->table(['Check', 'Result', 'Required action if failed'], array_map(fn ($row) => [$row['name'], $row['pass'] ? 'PASS' : 'FAIL', $row['pass'] ? '' : $row['action']], $checks));
            $this->comment('Technical checks do not replace P2/P3 approval, live HTTPS testing, payment/provider verification, or launch authorization.');
        }

        return $pass ? self::SUCCESS : self::FAILURE;
    }
}
