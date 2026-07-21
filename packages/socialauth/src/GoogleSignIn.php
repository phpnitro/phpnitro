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
 * google_sign_in equivalent — Google Identity Services JS SDK (client ID
 * only, no secret needed client-side), same "no vendor SDK dependency,
 * pure REST" philosophy as Engine\Firebase\/Payments\StripeCheckout.
 *
 * UNVERIFIED: no real Google Cloud OAuth client ID available in this
 * environment to test against — same confidence tier as Mapbox/Google
 * Maps/Firebase (implemented from official docs, never exercised for real).
 */
final class GoogleSignIn
{
    /**
     * Renders the GSI script tag + button container. $callbackAction is
     * posted the signed JWT credential as "credential" once the user picks
     * an account — verify it server-side with verifyIdToken() before
     * trusting it.
     */
    public static function button(string $clientId, string $callbackAction): string
    {
        $clientId = htmlspecialchars($clientId, ENT_QUOTES);
        // Not htmlspecialchars'd: this is embedded directly inside a
        // <script> block, which isn't HTML-entity-parsed — only the actual
        // HTML attributes (data-client_id) need that escaping.
        $action = json_encode($callbackAction, JSON_THROW_ON_ERROR);

        return <<<HTML
            <script src="https://accounts.google.com/gsi/client" async defer></script>
            <div id="g_id_onload"
                data-client_id="{$clientId}"
                data-callback="phpxGoogleSignInCallback"
                data-auto_prompt="false"></div>
            <div class="g_id_signin" data-type="standard"></div>
            <script>
              window.phpxGoogleSignInCallback = function (response) {
                window.phpxNav.submitAction({$action}, { credential: response.credential });
              };
            </script>
            HTML;
    }

    /**
     * Verifies the ID token against Google's tokeninfo endpoint (a plain
     * REST GET, no google/apiclient dependency) and returns the decoded
     * payload (sub, email, name...) if $clientId matches the token's
     * audience, null otherwise. A network call — do this once server-side,
     * never trust the client-supplied JWT payload directly.
     *
     * @return array<string, mixed>|null
     */
    public static function verifyIdToken(string $idToken, string $clientId): ?array
    {
        $response = @file_get_contents(
            'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken),
        );

        if ($response === false) {
            return null;
        }

        $payload = json_decode($response, true);
        if (!is_array($payload) || ($payload['aud'] ?? null) !== $clientId) {
            return null;
        }

        return $payload;
    }
}
