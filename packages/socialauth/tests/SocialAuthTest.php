<?php

namespace Engine\SocialAuth\Tests;

use Engine\SocialAuth\AppleSignIn;
use Engine\SocialAuth\FacebookSignIn;
use Engine\SocialAuth\GithubSignIn;
use Engine\SocialAuth\GoogleSignIn;
use Engine\SocialAuth\MicrosoftSignIn;
use Engine\SocialAuth\SlackSignIn;
use Engine\SocialAuth\XSignIn;
use PHPUnit\Framework\TestCase;

final class SocialAuthTest extends TestCase
{
    public function testGoogleOnClickIsAPureJsRedirectTrigger(): void
    {
        $js = GoogleSignIn::onClick('client-123', 'https://example.com/callback');

        $this->assertStringStartsWith('window.location.href = ', $js);
        $this->assertStringContainsString('accounts.google.com', $js);
        $this->assertStringContainsString('client_id=client-123', $js);
    }

    public function testEachProviderOnClickPointsAtItsOwnAuthorizeEndpoint(): void
    {
        $this->assertStringContainsString('login.microsoftonline.com', MicrosoftSignIn::onClick('c', 'https://x/cb'));
        $this->assertStringContainsString('github.com', GithubSignIn::onClick('c', 'https://x/cb'));
        $this->assertStringContainsString('facebook.com', FacebookSignIn::onClick('c', 'https://x/cb'));
        $this->assertStringContainsString('slack.com', SlackSignIn::onClick('c', 'https://x/cb'));
        $this->assertStringContainsString('appleid.apple.com', AppleSignIn::onClick('c', 'https://x/cb'));
    }

    public function testOnClickEscapesClientIdSafelyForJsEmbedding(): void
    {
        $js = GoogleSignIn::onClick('"; alert(1); //', 'https://example.com/cb');

        $this->assertStringNotContainsString('"; alert(1)', $js);
    }

    public function testExchangeCodeReturnsNullOnNetworkFailure(): void
    {
        $this->assertNull(GoogleSignIn::exchangeCode('bad-code', 'client', 'secret', 'https://example.com/cb'));
    }

    public function testXOnClickStoresPkceVerifierInSession(): void
    {
        $_SESSION = [];
        $js = XSignIn::onClick('client-x', 'https://example.com/cb');

        $this->assertArrayHasKey('phpx_x_oauth_code_verifier', $_SESSION);
        $this->assertStringContainsString('code_challenge_method=S256', $js);
        $this->assertStringContainsString('twitter.com', $js);
    }

    public function testXExchangeCodeReturnsNullWithoutAPriorOnClick(): void
    {
        $_SESSION = [];

        $this->assertNull(XSignIn::exchangeCode('some-code', 'client', '', 'https://example.com/cb'));
    }

    public function testAppleClientSecretProducesAValidEs256Jwt(): void
    {
        $keyPath = sys_get_temp_dir() . '/phpnitro_test_apple_key.pem';
        exec('openssl ecparam -name prime256v1 -genkey -noout -out ' . escapeshellarg($keyPath) . ' 2>/dev/null');
        if (!is_file($keyPath)) {
            $this->markTestSkipped('openssl CLI not available to generate a test EC key');
        }

        $jwt = AppleSignIn::clientSecret('TEAM1234567', 'KEY1234567', 'com.example.app', $keyPath, 3600);
        [$header, $claims, $signatureB64] = explode('.', $jwt);

        $headerJson = json_decode(base64_decode(strtr($header, '-_', '+/')), true);
        $this->assertSame('ES256', $headerJson['alg']);
        $this->assertSame('KEY1234567', $headerJson['kid']);

        $claimsJson = json_decode(base64_decode(strtr($claims, '-_', '+/')), true);
        $this->assertSame('TEAM1234567', $claimsJson['iss']);
        $this->assertSame('com.example.app', $claimsJson['sub']);
        $this->assertSame('https://appleid.apple.com', $claimsJson['aud']);

        // Round-trip the raw JOSE r||s signature back to DER and verify it
        // against the same key pair — the only way to mechanically confirm
        // the DER->JOSE conversion is correct without a real Apple account.
        $raw = base64_decode(strtr($signatureB64, '-_', '+/'));
        $this->assertSame(64, strlen($raw));

        $r = ltrim(substr($raw, 0, 32), "\x00");
        $s = ltrim(substr($raw, 32, 32), "\x00");
        if (ord($r[0]) > 0x7f) {
            $r = "\x00" . $r;
        }
        if (ord($s[0]) > 0x7f) {
            $s = "\x00" . $s;
        }
        $der = "\x02" . chr(strlen($r)) . $r . "\x02" . chr(strlen($s)) . $s;
        $der = "\x30" . chr(strlen($der)) . $der;

        $private = openssl_pkey_get_private('file://' . $keyPath);
        $details = openssl_pkey_get_details($private);
        $public = openssl_pkey_get_public($details['key']);

        $verified = openssl_verify("{$header}.{$claims}", $der, $public, OPENSSL_ALGO_SHA256);
        $this->assertSame(1, $verified);

        unlink($keyPath);
    }
}
