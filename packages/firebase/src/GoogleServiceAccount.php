<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Firebase;

/**
 * Google's "OAuth2 Service Account JWT Bearer" flow, pure PHP (openssl_sign,
 * no SDK) — the standard, stable way a server authenticates itself (not a
 * end user) against Google APIs. Shared by FirebaseMessaging (send) and
 * Firestore, both of which authenticate the exact same way with a service
 * account. Distinct from FirebaseAuth, which signs in END USERS via a web
 * API key instead — a different Google API (Identity Toolkit) with a
 * different credential shape entirely.
 *
 * @see https://developers.google.com/identity/protocols/oauth2/service-account
 */
final class GoogleServiceAccount
{
    /**
     * @return array{client_email: string, private_key: string, token_uri: string}|null
     */
    private static function load(string $serviceAccountJsonPath): ?array
    {
        if (!is_file($serviceAccountJsonPath)) {
            return null;
        }

        $json = file_get_contents($serviceAccountJsonPath);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if (!isset($data['client_email'], $data['private_key'])) {
            return null;
        }

        return [
            'client_email' => $data['client_email'],
            'private_key' => $data['private_key'],
            'token_uri' => $data['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        ];
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Builds and signs the JWT asserting this service account, for the
     * given scope — exposed separately from accessToken() so it can be
     * unit-tested (structure + signature) without a network call.
     */
    public static function signedJwt(string $serviceAccountJsonPath, string $scope, int $issuedAt): ?string
    {
        $account = self::load($serviceAccountJsonPath);
        if ($account === null) {
            return null;
        }

        $header = self::base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = self::base64UrlEncode(json_encode([
            'iss' => $account['client_email'],
            'scope' => $scope,
            'aud' => $account['token_uri'],
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ], JSON_THROW_ON_ERROR));

        $signingInput = "{$header}.{$claims}";
        $privateKey = openssl_pkey_get_private($account['private_key']);

        if ($privateKey === false) {
            return null;
        }

        $signed = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (!$signed) {
            return null;
        }

        return "{$signingInput}." . self::base64UrlEncode($signature);
    }

    /**
     * Exchanges the signed JWT for a short-lived (1h) access token via
     * POST {token_uri} — the actual network call, kept separate from
     * signedJwt() for the same testability reason.
     */
    public static function accessToken(string $serviceAccountJsonPath, string $scope): ?string
    {
        $account = self::load($serviceAccountJsonPath);
        if ($account === null) {
            return null;
        }

        $jwt = self::signedJwt($serviceAccountJsonPath, $scope, time());
        if ($jwt === null) {
            return null;
        }

        $params = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        try {
            $response = file_get_contents($account['token_uri'], false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $params,
                    'timeout' => 10,
                    'ignore_errors' => true,
                ],
            ]));

            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);

            return $data['access_token'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
