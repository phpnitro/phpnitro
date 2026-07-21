<?php

namespace Engine\SocialAuth\Tests;

use Engine\SocialAuth\AppleSignIn;
use Engine\SocialAuth\GoogleSignIn;
use PHPUnit\Framework\TestCase;

final class SocialAuthTest extends TestCase
{
    public function testGoogleButtonRendersClientIdAndAction(): void
    {
        $html = GoogleSignIn::button('123.apps.googleusercontent.com', 'googleLogin');

        $this->assertStringContainsString('data-client_id="123.apps.googleusercontent.com"', $html);
        $this->assertStringContainsString('"googleLogin"', $html);
    }

    public function testGoogleVerifyIdTokenReturnsNullOnNetworkFailure(): void
    {
        $this->assertNull(GoogleSignIn::verifyIdToken('not-a-real-token', 'client-id'));
    }

    public function testAppleButtonRendersClientIdAndRedirectUri(): void
    {
        $html = AppleSignIn::button('com.example.app', 'https://example.com/callback');

        $this->assertStringContainsString("clientId: 'com.example.app'", $html);
        $this->assertStringContainsString("redirectURI: 'https://example.com/callback'", $html);
    }
}
