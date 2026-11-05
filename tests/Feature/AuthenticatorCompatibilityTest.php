<?php

namespace Tests\Feature;

use App\Services\Security\AdminTwoFactor;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class AuthenticatorCompatibilityTest extends TestCase
{
    public function test_six_digit_sha1_matches_published_rfc6238_vectors(): void
    {
        // Public RFC 6238 Appendix B key; expected six-digit suffixes of its SHA-1 vectors.
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        foreach ([59 => '287082', 1111111109 => '081804', 1111111111 => '050471', 1234567890 => '005924', 2000000000 => '279037'] as $timestamp => $code) {
            $this->travelTo(CarbonImmutable::createFromTimestamp($timestamp));
            $this->assertSame(intdiv($timestamp, 30), app(AdminTwoFactor::class)->step($secret, $code));
        }
    }

    public function test_new_keys_and_provisioning_uri_use_standard_authenticator_settings(): void
    {
        $service = app(AdminTwoFactor::class);
        $secret = $service->generate();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/D', $secret);
        $uri = $service->provisioningUri($secret, 'operator@example.test');
        $this->assertSame('otpauth', parse_url($uri, PHP_URL_SCHEME));
        $this->assertSame('totp', parse_url($uri, PHP_URL_HOST));
        parse_str(parse_url($uri, PHP_URL_QUERY), $parameters);
        $this->assertSame($secret, $parameters['secret']);
        $this->assertSame('FarSell', $parameters['issuer']);
        $this->assertSame('SHA1', $parameters['algorithm'] ?? 'SHA1');
        $this->assertEquals(6, $parameters['digits'] ?? 6);
        $this->assertEquals(30, $parameters['period'] ?? 30);
        $dataUri = $service->qrDataUri($secret, 'operator@example.test');
        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
        $bytes = base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true);
        $this->assertSame(IMAGETYPE_PNG, getimagesizefromstring($bytes)[2]);
    }
}
