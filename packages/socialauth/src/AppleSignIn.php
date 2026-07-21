<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\SocialAuth;

/**
 * sign_in_with_apple equivalent — the Apple ID JS SDK ("Sign in with
 * Apple" button + form_post flow to a redirect URI your backend handles).
 *
 * UNVERIFIED, MORE INCOMPLETE than GoogleSignIn: no Apple Developer
 * account/Services ID available in this environment. button() (the
 * client-side JS SDK integration) is real and complete. Server-side ID
 * token verification is NOT implemented here — it requires converting
 * Apple's JWKS (https://appleid.apple.com/auth/keys, modulus/exponent) into
 * a PEM public key before openssl_verify() can check the RS256 signature,
 * a real cryptographic operation this project has never exercised against
 * a genuine Apple-issued token. Shipping an unverified JWKS-to-PEM routine
 * would be worse than admitting the gap honestly — do NOT trust an Apple
 * id_token in production against this package alone; verify it server-side
 * with a maintained JWT library first.
 */
final class AppleSignIn
{
    public static function button(string $clientId, string $redirectUri, string $scope = 'name email'): string
    {
        $clientId = htmlspecialchars($clientId, ENT_QUOTES);
        $redirectUri = htmlspecialchars($redirectUri, ENT_QUOTES);
        $scope = htmlspecialchars($scope, ENT_QUOTES);

        return <<<HTML
            <script src="https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js" async defer></script>
            <div id="appleid-signin" data-color="black" data-border="true" data-type="sign in"></div>
            <script>
              document.addEventListener('DOMContentLoaded', function () {
                if (window.AppleID) {
                  window.AppleID.auth.init({
                    clientId: '{$clientId}',
                    scope: '{$scope}',
                    redirectURI: '{$redirectUri}',
                    responseMode: 'form_post',
                  });
                }
              });
            </script>
            HTML;
    }
}
