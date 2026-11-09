<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Existing business-flow tests isolate authorization from interactive challenges.
        // Dedicated account-security tests explicitly turn these gates back on.
        config(['security.admin_mfa_required' => false, 'security.seller_mfa_required' => false, 'security.login_otp_required' => false, 'security.sensitive_confirmation_required' => false]);
    }
}
