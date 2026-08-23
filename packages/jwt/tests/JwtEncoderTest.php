<?php

namespace Engine\Jwt\Tests;

use Engine\Jwt\JwtDecoder;
use Engine\Jwt\JwtEncoder;
use PHPUnit\Framework\TestCase;

final class JwtEncoderTest extends TestCase
{
    public function testEncodeProducesAThreeSegmentToken(): void
    {
        $token = JwtEncoder::encode(['sub' => '42'], 'top-secret');

        $this->assertCount(3, explode('.', $token));
    }

    public function testEncodedPayloadRoundTripsThroughJwtDecoder(): void
    {
        $token = JwtEncoder::encode(['sub' => '42', 'name' => 'Ada'], 'top-secret');

        $this->assertSame(['sub' => '42', 'name' => 'Ada'], JwtDecoder::payload($token));
    }

    public function testEncodeSetsExpFromExpiresInSeconds(): void
    {
        $before = time();
        $token = JwtEncoder::encode(['sub' => '42'], 'top-secret', expiresInSeconds: 3600);
        $after = time();

        $exp = JwtDecoder::payload($token)['exp'];
        $this->assertGreaterThanOrEqual($before + 3600, $exp);
        $this->assertLessThanOrEqual($after + 3600, $exp);
    }

    public function testEncodeWithNoExpiryLeavesNoExpClaim(): void
    {
        $token = JwtEncoder::encode(['sub' => '42'], 'top-secret');

        $this->assertArrayNotHasKey('exp', JwtDecoder::payload($token));
    }

    public function testVerifyAcceptsATokenSignedWithTheSameSecret(): void
    {
        $token = JwtEncoder::encode(['sub' => '42'], 'top-secret');

        $this->assertTrue(JwtEncoder::verify($token, 'top-secret'));
    }

    public function testVerifyRejectsATokenSignedWithADifferentSecret(): void
    {
        $token = JwtEncoder::encode(['sub' => '42'], 'top-secret');

        $this->assertFalse(JwtEncoder::verify($token, 'wrong-secret'));
    }

    public function testVerifyRejectsATamperedPayload(): void
    {
        $token = JwtEncoder::encode(['sub' => '42', 'role' => 'user'], 'top-secret');
        [$header, , $signature] = explode('.', $token);
        $tamperedBody = rtrim(strtr(base64_encode(json_encode(['sub' => '42', 'role' => 'admin'])), '+/', '-_'), '=');

        $this->assertFalse(JwtEncoder::verify("{$header}.{$tamperedBody}.{$signature}", 'top-secret'));
    }

    public function testVerifyRejectsAMalformedToken(): void
    {
        $this->assertFalse(JwtEncoder::verify('not-a-jwt', 'top-secret'));
    }
}
