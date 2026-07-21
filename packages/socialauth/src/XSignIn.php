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
 * X (formerly Twitter) OAuth2 — the only provider in this package that
 * mandates PKCE. The code_verifier has to survive between onClick()'s
 * redirect and exchangeCode() handling the callback, so it's round-tripped
 * through the session (started by public/index.php for the whole app
 * already) rather than requiring the caller to thread it through
 * themselves.
 *
 * UNVERIFIED — no real X Developer App available in this environment.
 */
final class XSignIn extends OAuthProvider
{
    private const SESSION_KEY = 'phpx_x_oauth_code_verifier';

    protected static function authorizeEndpoint(): string
    {
        return 'https://twitter.com/i/oauth2/authorize';
    }

    protected static function tokenEndpoint(): string
    {
        return 'https://api.twitter.com/2/oauth2/token';
    }

    protected static function userInfoEndpoint(): ?string
    {
        return 'https://api.twitter.com/2/users/me?user.fields=id,name,username';
    }

    protected static function defaultScope(): string
    {
        return 'users.read tweet.read';
    }

    public static function onClick(string $clientId, string $redirectUri, ?string $scope = null): string
    {
        $verifier = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY] = $verifier;
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scope ?? self::defaultScope(),
            'state' => bin2hex(random_bytes(8)),
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ];

        $url = self::authorizeEndpoint() . '?' . http_build_query($params);

        return 'window.location.href = ' . json_encode($url, JSON_THROW_ON_ERROR);
    }

    public static function exchangeCode(string $code, string $clientId, string $clientSecret, string $redirectUri): ?array
    {
        $verifier = $_SESSION[self::SESSION_KEY] ?? null;
        if ($verifier === null) {
            return null;
        }
        unset($_SESSION[self::SESSION_KEY]);

        $tokenResponse = self::post(self::tokenEndpoint(), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $verifier,
        ]);

        if ($tokenResponse === null || !isset($tokenResponse['access_token'])) {
            return null;
        }

        $userInfoResponse = self::get(self::userInfoEndpoint(), self::userInfoHeaders($tokenResponse['access_token']));
        if ($userInfoResponse === null) {
            return null;
        }

        return self::normalize($tokenResponse, $userInfoResponse);
    }

    protected static function normalize(array $tokenResponse, ?array $userInfoResponse): array
    {
        $data = $userInfoResponse['data'] ?? [];

        return [
            'id' => (string) ($data['id'] ?? ''),
            'email' => null,
            'name' => $data['name'] ?? $data['username'] ?? null,
        ];
    }
}
