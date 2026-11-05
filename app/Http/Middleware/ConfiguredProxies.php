<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

class ConfiguredProxies extends TrustProxies
{
    protected function setTrustedProxyIpAddresses(Request $request): void
    {
        // Do not inherit the framework's managed-host wildcard fallback.
        $request->setTrustedProxies(config('security.trusted_proxies', []), $this->getTrustedHeaderNames());
    }

    protected function getTrustedHeaderNames(): int
    {
        // X-Forwarded-Host must not control generated links or OAuth redirects.
        return Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT;
    }
}
