<?php

namespace Engine\Jwt\Tests;

use Engine\Jwt\JwtDecoder;
use PHPUnit\Framework\TestCase;

final class JwtDecoderTest extends TestCase
{
    private static function makeToken(array $header, array $payload): string
    {
        $encode = static fn (array $data): string => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $encode($header) . '.' . $encode($payload) . '.signature-not-verified';
    }

    public function testDecodesHeaderAndPayload(): void
    {
        $token = self::makeToken(['alg' => 'HS256', 'typ' => 'JWT'], ['sub' => '42', 'name' => 'Ada']);

        $decoded = JwtDecoder::decode($token);

        $this->assertSame(['alg' => 'HS256', 'typ' => 'JWT'], $decoded['header']);
        $this->assertSame(['sub' => '42', 'name' => 'Ada'], $decoded['payload']);
    }

    public function testPayloadShortcut(): void
    {
        $token = self::makeToken(['alg' => 'HS256'], ['sub' => '42']);
        $this->assertSame(['sub' => '42'], JwtDecoder::payload($token));
    }

    public function testIsExpiredForPastExpiry(): void
    {
        $token = self::makeToken(['alg' => 'HS256'], ['exp' => time() - 3600]);
        $this->assertTrue(JwtDecoder::isExpired($token));
    }

    public function testIsExpiredForFutureExpiry(): void
    {
        $token = self::makeToken(['alg' => 'HS256'], ['exp' => time() + 3600]);
        $this->assertFalse(JwtDecoder::isExpired($token));
    }

    public function testIsExpiredWithNoExpClaimIsFalse(): void
    {
        $token = self::makeToken(['alg' => 'HS256'], ['sub' => '42']);
        $this->assertFalse(JwtDecoder::isExpired($token));
    }

    public function testDecodeRejectsMalformedToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        JwtDecoder::decode('not-a-jwt');
    }
}
