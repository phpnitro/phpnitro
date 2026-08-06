<?php

namespace Engine\Crypto\Tests;

use Engine\Crypto\Crypto;
use PHPUnit\Framework\TestCase;

final class CryptoTest extends TestCase
{
    public function testSha256MatchesNativeHash(): void
    {
        $this->assertSame(hash('sha256', 'hello'), Crypto::sha256('hello'));
    }

    public function testHmacSha256MatchesNativeHashHmac(): void
    {
        $this->assertSame(hash_hmac('sha256', 'data', 'key'), Crypto::hmacSha256('data', 'key'));
    }

    public function testVerifyHmacAcceptsCorrectSignature(): void
    {
        $signature = Crypto::hmacSha256('payload', 'secret');
        $this->assertTrue(Crypto::verifyHmac('payload', 'secret', $signature));
    }

    public function testVerifyHmacRejectsWrongSignature(): void
    {
        $this->assertFalse(Crypto::verifyHmac('payload', 'secret', 'wrong'));
    }

    public function testRandomTokenLengthAndUniqueness(): void
    {
        $token = Crypto::randomToken(16);
        $this->assertSame(32, strlen($token));
        $this->assertNotSame($token, Crypto::randomToken(16));
    }
}
