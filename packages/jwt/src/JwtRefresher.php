<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Jwt;

/**
 * Exchanges an expired/expiring access token's refresh token for a new
 * pair, against your own backend's refresh endpoint — same REST,
 * server-to-server idiom as Engine\Payments\* (file_get_contents()/
 * stream_context_create(), no curl: the PHP binary cross-compiled for
 * Android has no curl extension, see Engine\Payments\Feexpay's own
 * docblock).
 *
 * Pair with JwtDecoder::isExpired() to know WHEN to call refresh() —
 * this class only does the HTTP round-trip, it has no opinion on your
 * own storage/retry policy for the resulting tokens.
 *
 * Assumes the OAuth2 refresh_token grant's conventional shape
 * (POST {"refresh_token": "..."}, JSON response with `access_token`/
 * `refresh_token`) — adjust $endpoint's own server-side handler to
 * match if your backend uses a different one; this class doesn't
 * invent a PhpNitro-specific protocol.
 */
final class JwtRefresher
{
    /**
     * @return array{access_token: string, refresh_token: string}|false
     */
    public static function refresh(string $endpoint, string $refreshToken): array|false
    {
        $response = @file_get_contents($endpoint, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => json_encode(['refresh_token' => $refreshToken], JSON_THROW_ON_ERROR),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]));

        if ($response === false) {
            return false;
        }

        $data = json_decode($response, true);
        $accessToken = is_array($data) ? ($data['access_token'] ?? null) : null;
        $newRefreshToken = is_array($data) ? ($data['refresh_token'] ?? null) : null;

        if (!is_string($accessToken) || !is_string($newRefreshToken)) {
            return false;
        }

        return ['access_token' => $accessToken, 'refresh_token' => $newRefreshToken];
    }
}
