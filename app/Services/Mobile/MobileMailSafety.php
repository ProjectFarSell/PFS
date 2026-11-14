<?php

namespace App\Services\Mobile;

use RuntimeException;

class MobileMailSafety
{
    public function ensure(?string $mailer = null, array $seen = []): void
    {
        $mailer ??= config('mail.default');
        if (isset($seen[$mailer])) {
            throw new RuntimeException('Invalid mailer configuration.');
        }
        $seen[$mailer] = true;
        $config = config('mail.mailers.'.$mailer, []);
        if (($config['transport'] ?? null) === 'log') {
            throw new RuntimeException('Use SMTP or a private sandbox inbox for mobile security and order messages.');
        }
        foreach ($config['mailers'] ?? [] as $child) {
            $this->ensure($child, $seen);
        }
    }
}
