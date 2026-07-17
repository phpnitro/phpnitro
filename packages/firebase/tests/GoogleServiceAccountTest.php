<?php

namespace Engine\Firebase\Tests;

use Engine\Firebase\GoogleServiceAccount;
use PHPUnit\Framework\TestCase;

/**
 * No network needed: a throwaway RSA keypair generated locally stands in
 * for a real service account, letting the JWT's structure AND its RS256
 * signature be verified independently (openssl_verify against the public
 * half) without ever calling Google's token endpoint.
 */
final class GoogleServiceAccountTest extends TestCase
{
    private string $serviceAccountPath;
    private string $publicKeyPem;

    protected function setUp(): void
    {
        $keyPair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($keyPair, $privateKeyPem);
        $this->publicKeyPem = openssl_pkey_get_details($keyPair)['key'];

        $this->serviceAccountPath = tempnam(sys_get_temp_dir(), 'phpx_sa_');
        file_put_contents($this->serviceAccountPath, json_encode([
            'client_email' => 'test@example.iam.gserviceaccount.com',
            'private_key' => $privateKeyPem,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
    }

    protected function tearDown(): void
    {
        unlink($this->serviceAccountPath);
    }

    public function testSignedJwtHasCorrectHeaderAndClaims(): void
    {
        $jwt = GoogleServiceAccount::signedJwt($this->serviceAccountPath, 'https://www.googleapis.com/auth/firebase.messaging', 1700000000);

        $this->assertNotNull($jwt);
        [$header, $claims] = explode('.', $jwt);

        $decodedHeader = json_decode($this->base64UrlDecode($header), true);
        $decodedClaims = json_decode($this->base64UrlDecode($claims), true);

        $this->assertSame(['alg' => 'RS256', 'typ' => 'JWT'], $decodedHeader);
        $this->assertSame('test@example.iam.gserviceaccount.com', $decodedClaims['iss']);
        $this->assertSame('https://www.googleapis.com/auth/firebase.messaging', $decodedClaims['scope']);
        $this->assertSame('https://oauth2.googleapis.com/token', $decodedClaims['aud']);
        $this->assertSame(1700000000, $decodedClaims['iat']);
        $this->assertSame(1700003600, $decodedClaims['exp']);
    }

    public function testSignedJwtSignatureIsValidRs256(): void
    {
        $jwt = GoogleServiceAccount::signedJwt($this->serviceAccountPath, 'scope', 1700000000);

        [$header, $claims, $signature] = explode('.', $jwt);
        $signingInput = "{$header}.{$claims}";

        $verified = openssl_verify($signingInput, $this->base64UrlDecode($signature), $this->publicKeyPem, OPENSSL_ALGO_SHA256);

        $this->assertSame(1, $verified);
    }

    public function testSignedJwtReturnsNullWhenServiceAccountFileIsMissing(): void
    {
        $this->assertNull(GoogleServiceAccount::signedJwt('/nonexistent/path.json', 'scope', 1700000000));
    }

    public function testSignedJwtReturnsNullWhenPrivateKeyFieldIsMissing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phpx_sa_bad_');
        file_put_contents($path, json_encode(['client_email' => 'x@example.com']));

        $this->assertNull(GoogleServiceAccount::signedJwt($path, 'scope', 1700000000));

        unlink($path);
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }
}
