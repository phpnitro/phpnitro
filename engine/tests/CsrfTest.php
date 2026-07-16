<?php

namespace Engine\Tests;

use Engine\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testTokenIsStablePerSession(): void
    {
        $this->assertSame(Csrf::token(), Csrf::token());
    }

    public function testVerifyAcceptsValidToken(): void
    {
        $this->assertTrue(Csrf::verify(Csrf::token()));
    }

    public function testVerifyRejectsInvalidTokens(): void
    {
        Csrf::token();

        $this->assertFalse(Csrf::verify('forged'));
        $this->assertFalse(Csrf::verify(null));
    }

    public function testVerifyRejectsWhenNoTokenIssued(): void
    {
        $this->assertFalse(Csrf::verify('anything'));
    }

    public function testFieldContainsToken(): void
    {
        $this->assertStringContainsString(Csrf::token(), Csrf::field());
    }
}
