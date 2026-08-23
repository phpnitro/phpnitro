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
 * Signs/verifies a JWT — HS256 (hash_hmac('sha256', ...)), no library
 * needed, same "no dependency for what's just base64url + JSON + an
 * HMAC" philosophy as JwtDecoder. The natural counterpart to
 * JwtDecoder: that class deliberately never checks a signature (a
 * client reading claims out of a token it trusts already); this class
 * is for whichever side actually HOLDS the secret and can therefore
 * mint/authenticate tokens for real.
 *
 * **Where $secret is allowed to live matters more than usual in this
 * framework.** PhpNitro's own PHP runs ON THE DEVICE for a real shipped
 * app — `.env` is bundled straight into the APK as `env`
 * (see bin/phpx's cmdBundleAndroid()), extractable from any decompiled
 * APK. Calling encode() with a secret sourced from THIS app's own
 * `.env` means every install ships that secret — anyone can then mint
 * their own valid tokens. Only safe when $secret lives on a real
 * server you control that this app talks to over HTTP (this app then
 * only ever calls verify()/decode() on tokens IT receives, never
 * encode() with that same secret) — never bundle a signing secret into
 * an app you intend to distribute.
 */
final class JwtEncoder
{
    /**
     * $payload's `exp` is overwritten by $expiresInSeconds when given
     * (time() + $expiresInSeconds) — pass null to mint a token with no
     * expiry (or to keep an `exp` you already set in $payload yourself).
     *
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload, string $secret, ?int $expiresInSeconds = null): string
    {
        if ($expiresInSeconds !== null) {
            $payload['exp'] = time() + $expiresInSeconds;
        }

        $header = self::encodeSegment(['alg' => 'HS256', 'typ' => 'JWT']);
        $body = self::encodeSegment($payload);
        $signature = self::sign("{$header}.{$body}", $secret);

        return "{$header}.{$body}.{$signature}";
    }

    /**
     * Checks the signature only — NOT expiry (see JwtDecoder::isExpired()
     * for that, a separate concern: a well-signed token can still be
     * expired, and there's no reason to force both checks together for
     * a caller that only cares about one). A malformed token (wrong
     * number of segments, unreadable base64url) is simply not verified,
     * same fail-quiet convention as JwtDecoder's own methods throwing
     * only on genuinely malformed input, never on "just isn't valid".
     */
    public static function verify(string $token, string $secret): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $body, $signature] = $parts;
        $expected = self::sign("{$header}.{$body}", $secret);

        return hash_equals($expected, $signature);
    }

    private static function sign(string $data, string $secret): string
    {
        return self::base64UrlEncode(hash_hmac('sha256', $data, $secret, true));
    }

    /** @param array<string, mixed> $data */
    private static function encodeSegment(array $data): string
    {
        return self::base64UrlEncode(json_encode($data, JSON_THROW_ON_ERROR));
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
