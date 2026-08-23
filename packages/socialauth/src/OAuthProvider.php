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
 * Shared standard OAuth2 Authorization Code flow every provider in this
 * package (Google, Microsoft, GitHub, Facebook) is built on — Apple also
 * extends this but overrides token exchange for its ES256 client-secret
 * JWT requirement (see AppleSignIn). Google itself is handled by a real
 * native SDK instead (Credential Manager — see NativeDeviceBridge.kt's
 * signInWithGoogle()), not this class; GoogleSignIn isn't restored here
 * for that reason, the other four have no equivalent native Android SDK
 * this framework bundles, so a standard browser-redirect OAuth flow is
 * the actual native-appropriate approach for them, not a compromise.
 *
 * authorizeUrl() replaces the pre-native-render onClick() (which returned
 * a `window.location.href = ...` JS string — there is no WebView/JS
 * runtime left to execute that in). NativeDeviceBridge.kt's
 * startOAuthFlow() opens the URL this returns in a Custom Tab; the
 * provider's own redirect lands back in the app via an App Link
 * (AndroidManifest's oauth-callback intent-filter, see
 * NativeRenderPocActivity's onNewIntent()), which extracts "code" and
 * reports it back through the same generic "device capability reports
 * its result, then a normal refetch" shape every other device: capability
 * already uses. The developer's own action handler (public/index.php)
 * then calls exchangeCode() with that code and gets back normalized user
 * info (id/email/name) to create a session/account with — the whole
 * authenticate-and-fetch-profile round trip in one call.
 */
abstract class OAuthProvider
{
    abstract protected static function authorizeEndpoint(): string;

    abstract protected static function tokenEndpoint(): string;

    abstract protected static function defaultScope(): string;

    /**
     * Null for providers whose token response already includes the
     * profile (Slack, with the right scopes) — override userInfoHeaders()
     * too if the provider needs something other than a bearer token.
     */
    protected static function userInfoEndpoint(): ?string
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    protected static function extraAuthorizeParams(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    protected static function userInfoHeaders(string $accessToken): array
    {
        return ['Authorization: Bearer ' . $accessToken];
    }

    /**
     * Normalizes each provider's own field names into a common shape.
     * `access_token` is $tokenResponse's own OAuth2 access token, passed
     * through as-is (not provider-specific) — needed as-is by
     * Engine\Firebase\FirebaseAuth::signInWithFacebookAccessToken()/
     * signInWithGithubAccessToken(), which authenticate the token
     * against its issuing provider server-side rather than trusting
     * this app's own OAuth exchange as proof by itself.
     *
     * @param array<string, mixed> $tokenResponse
     * @param array<string, mixed>|null $userInfoResponse
     * @return array{id: string, email: ?string, name: ?string, access_token: ?string}
     */
    abstract protected static function normalize(array $tokenResponse, ?array $userInfoResponse): array;

    public static function authorizeUrl(string $clientId, string $redirectUri, ?string $scope = null): string
    {
        $params = array_merge([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scope ?? static::defaultScope(),
        ], static::extraAuthorizeParams());

        return static::authorizeEndpoint() . '?' . http_build_query($params);
    }

    /**
     * @return array{id: string, email: ?string, name: ?string, access_token: ?string}|null null on any failure (network, denied, bad code) — nothing to salvage, caller shows a generic "connexion échouée".
     */
    public static function exchangeCode(string $code, string $clientId, string $clientSecret, string $redirectUri): ?array
    {
        $tokenResponse = self::post(static::tokenEndpoint(), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
        ]);

        if ($tokenResponse === null || !isset($tokenResponse['access_token'])) {
            return null;
        }

        $userInfoResponse = null;
        $endpoint = static::userInfoEndpoint();
        if ($endpoint !== null) {
            $userInfoResponse = self::get($endpoint, static::userInfoHeaders($tokenResponse['access_token']));
            if ($userInfoResponse === null) {
                return null;
            }
        }

        return static::normalize($tokenResponse, $userInfoResponse);
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>|null
     */
    protected static function post(string $url, array $fields): ?array
    {
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => http_build_query($fields),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]));

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, mixed>|null
     */
    protected static function get(string $url, array $headers): ?array
    {
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers) . "\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]));

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        return is_array($data) ? $data : null;
    }
}
