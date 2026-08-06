<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Cloudinary;

/**
 * Image upload/transform/delete via Cloudinary's REST API directly
 * (file_get_contents()/stream_context_create(), hand-built multipart
 * body) — no `cloudinary/cloudinary_php` SDK dependency, same reasoning
 * as Engine\Payments\Feexpay's own docblock: that SDK's HTTP layer
 * (Guzzle, ultimately curl) has no guarantee of working on the PHP
 * binary cross-compiled for Android (android/README.md's php-ndk
 * build has no `curl` extension, confirmed the hard way for Feexpay).
 * Only `openssl` (for the https:// stream wrapper) is needed here, and
 * that gap is already closed for Feexpay/Stripe/OAuthProvider.
 *
 * Every write call (upload/destroy) is SIGNED — Cloudinary's own spec:
 * every parameter except file/cloud_name/resource_type/api_key/signature,
 * sorted alphabetically by key, joined as "key=value&key2=value2"
 * (raw values, not URL-encoded), api_secret appended directly (no
 * separator), then SHA-1'd. Get this wrong and Cloudinary rejects the
 * request outright with "Invalid Signature" — verified here only as a
 * pure string/hash computation (deterministic, easy to hand-check
 * against Cloudinary's own documented example), NOT against a real
 * Cloudinary account (none available in this environment) — same
 * confidence tier as AppleSignIn/GithubSignIn elsewhere in this
 * framework.
 */
final class Cloudinary
{
    private const BASE_URL = 'https://api.cloudinary.com/v1_1';

    /**
     * @param array<string, string> $options Any Cloudinary upload
     *   parameter that gets SIGNED (folder, public_id, tags,
     *   overwrite...) — see Cloudinary's own upload API reference for
     *   the full list. Do not put unsigned parameters (like
     *   upload_preset for unsigned uploads) here; this method only
     *   supports the signed flow.
     * @return array{public_id: string, secure_url: string, url: string, format: string, bytes: int}|array{error: string}
     */
    public static function upload(string $cloudName, string $apiKey, string $apiSecret, string $filePath, array $options = []): array
    {
        if (!is_file($filePath)) {
            return ['error' => "file not found: {$filePath}"];
        }

        $fileContents = file_get_contents($filePath);
        if ($fileContents === false) {
            return ['error' => "unable to read file: {$filePath}"];
        }

        $params = $options;
        $params['timestamp'] = (string) time();
        $signature = self::sign($params, $apiSecret);

        $fields = $params;
        $fields['api_key'] = $apiKey;
        $fields['signature'] = $signature;

        $boundary = 'PhpNitroCloudinary' . bin2hex(random_bytes(16));
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\n"
                . "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
        }
        $body .= "--{$boundary}\r\n"
            . 'Content-Disposition: form-data; name="file"; filename="' . basename($filePath) . "\"\r\n"
            . "Content-Type: application/octet-stream\r\n\r\n"
            . $fileContents . "\r\n"
            . "--{$boundary}--\r\n";

        $response = self::send("/{$cloudName}/image/upload", "multipart/form-data; boundary={$boundary}", $body, 20);

        if ($response['error'] !== null) {
            return ['error' => $response['error']];
        }

        return $response['data'];
    }

    /**
     * Builds a delivery URL with Cloudinary's transformation
     * mini-language — $transformations is `parameter => value`
     * (e.g. ['width' => 400, 'height' => 400, 'crop' => 'fill',
     * 'quality' => 'auto']) turned into "w_400,h_400,c_fill,q_auto/".
     * No network call — this is pure string building, Cloudinary
     * resolves the actual transformed image lazily on first request to
     * the URL.
     *
     * @param array<string, string|int> $transformations
     */
    public static function url(string $cloudName, string $publicId, array $transformations = []): string
    {
        // Cloudinary's own shorthand for the handful of parameters that
        // don't just take their full name as the URL segment prefix —
        // everything else falls back to its own key name (already how
        // Cloudinary accepts most parameters, e.g. "radius_20").
        $shorthand = [
            'width' => 'w', 'height' => 'h', 'crop' => 'c', 'gravity' => 'g',
            'quality' => 'q', 'format' => 'f', 'radius' => 'r', 'effect' => 'e',
            'angle' => 'a', 'opacity' => 'o', 'x' => 'x', 'y' => 'y', 'zoom' => 'z',
        ];

        $segments = [];
        foreach ($transformations as $key => $value) {
            $prefix = $shorthand[$key] ?? $key;
            $segments[] = "{$prefix}_{$value}";
        }

        $transformPath = $segments === [] ? '' : implode(',', $segments) . '/';

        return "https://res.cloudinary.com/{$cloudName}/image/upload/{$transformPath}{$publicId}";
    }

    public static function destroy(string $cloudName, string $apiKey, string $apiSecret, string $publicId): bool
    {
        $params = ['public_id' => $publicId, 'timestamp' => (string) time()];
        $signature = self::sign($params, $apiSecret);

        $body = http_build_query(array_merge($params, ['api_key' => $apiKey, 'signature' => $signature]));
        $response = self::send("/{$cloudName}/image/destroy", 'application/x-www-form-urlencoded', $body, 10);

        return $response['error'] === null && ($response['data']['result'] ?? '') === 'ok';
    }

    /** @param array<string, string> $params */
    private static function sign(array $params, string $apiSecret): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = "{$key}={$value}";
        }

        return sha1(implode('&', $pairs) . $apiSecret);
    }

    /** @return array{data: array<string, mixed>, error: null}|array{data: null, error: string} */
    private static function send(string $path, string $contentType, string $body, int $timeoutSeconds): array
    {
        $response = @file_get_contents(self::BASE_URL . $path, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: {$contentType}\r\n",
                'content' => $body,
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]));

        if ($response === false) {
            return ['data' => null, 'error' => 'network_error'];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['data' => null, 'error' => 'invalid_response'];
        }
        if (isset($data['error'])) {
            return ['data' => null, 'error' => is_array($data['error']) ? ($data['error']['message'] ?? 'unknown_error') : (string) $data['error']];
        }

        return ['data' => $data, 'error' => null];
    }
}
