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
 * sign_in_with_apple equivalent. Apple is the one provider here that
 * doesn't take a static client_secret string — it requires a short-lived
 * ES256-signed JWT generated from a private key downloaded once from
 * Apple Developer's "Keys" section, built by clientSecret() below (same
 * "sign our own JWT with openssl" idiom as
 * Engine\Firebase\GoogleServiceAccount, RS256 there vs ES256 here). Apple
 * also has no userinfo REST endpoint: name/email only ever arrive inside
 * the id_token (a JWT) in the token response, and only on the user's
 * FIRST authorization — store them yourself the first time, Apple won't
 * send them again on subsequent logins.
 *
 * UNVERIFIED, MORE INCOMPLETE than the other providers: no Apple Developer
 * account/Services ID/private key available in this environment.
 * normalize() decodes the id_token's payload WITHOUT verifying its RS256
 * signature against Apple's published JWKS (https://appleid.apple.com/auth/keys)
 * — acceptable here only because the token just arrived directly from
 * Apple's own token endpoint over TLS in exchangeCode(), not from an
 * untrusted client. Do NOT reuse decodeUnverifiedPayload() to trust an
 * id_token handed to you from anywhere else (e.g. posted by client JS)
 * without verifying the signature first.
 */
final class AppleSignIn extends OAuthProvider
{
    protected static function authorizeEndpoint(): string
    {
        return 'https://appleid.apple.com/auth/authorize';
    }

    protected static function tokenEndpoint(): string
    {
        return 'https://appleid.apple.com/auth/token';
    }

    protected static function defaultScope(): string
    {
        return 'name email';
    }

    protected static function extraAuthorizeParams(): array
    {
        return ['response_mode' => 'form_post'];
    }

    protected static function normalize(array $tokenResponse, ?array $userInfoResponse): array
    {
        $claims = isset($tokenResponse['id_token'])
            ? self::decodeUnverifiedPayload($tokenResponse['id_token'])
            : [];

        return [
            'id' => (string) ($claims['sub'] ?? ''),
            'email' => $claims['email'] ?? null,
            'name' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeUnverifiedPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return [];
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $claims = $payload !== false ? json_decode($payload, true) : null;

        return is_array($claims) ? $claims : [];
    }

    /**
     * ES256-signed JWT Apple requires as the "client_secret" passed to
     * exchangeCode() — $privateKeyPath is the .p8 file downloaded once
     * from Apple Developer, $keyId its 10-character Key ID, $teamId your
     * 10-character Apple Developer Team ID. Valid for $ttlSeconds (Apple
     * allows up to 6 months; regenerate well before it expires since an
     * expired one fails every login attempt until replaced).
     */
    public static function clientSecret(
        string $teamId,
        string $keyId,
        string $clientId,
        string $privateKeyPath,
        int $ttlSeconds = 15777000,
    ): string {
        $privateKey = openssl_pkey_get_private('file://' . $privateKeyPath);
        if ($privateKey === false) {
            throw new \RuntimeException('Unable to read Apple private key at ' . $privateKeyPath);
        }

        $now = time();
        $header = self::base64UrlEncode(json_encode(['alg' => 'ES256', 'kid' => $keyId], JSON_THROW_ON_ERROR));
        $claims = self::base64UrlEncode(json_encode([
            'iss' => $teamId,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
            'aud' => 'https://appleid.apple.com',
            'sub' => $clientId,
        ], JSON_THROW_ON_ERROR));

        $signingInput = "{$header}.{$claims}";
        openssl_sign($signingInput, $derSignature, $privateKey, OPENSSL_ALGO_SHA256);

        return "{$signingInput}." . self::base64UrlEncode(self::derToJoseSignature($derSignature));
    }

    /**
     * openssl_sign() on an EC key returns an ASN.1 DER-encoded signature
     * (30 len 02 rlen r 02 slen s); JOSE/JWT's ES256 requires the raw,
     * fixed-width r||s concatenation instead (64 bytes for P-256) — using
     * the DER bytes directly produces a syntactically-different signature
     * Apple's verifier would reject. A real, deterministic conversion
     * (not a guess), same "value, not just length, right-padded/stripped"
     * DER integer handling every JOSE library does for EC signatures.
     */
    private static function derToJoseSignature(string $der): string
    {
        $offset = 2; // skip SEQUENCE tag + length byte
        $offset += 1; // skip INTEGER tag for r
        $rLen = ord($der[$offset]);
        $offset += 1;
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;

        $offset += 1; // skip INTEGER tag for s
        $sLen = ord($der[$offset]);
        $offset += 1;
        $s = substr($der, $offset, $sLen);

        return self::fixedWidth($r, 32) . self::fixedWidth($s, 32);
    }

    /**
     * DER integers are minimal-length and left-zero-padded with a leading
     * 0x00 whenever the high bit would otherwise be set (to keep them
     * unsigned) — both need undoing to get exactly $width bytes.
     */
    private static function fixedWidth(string $value, int $width): string
    {
        $value = ltrim($value, "\x00");
        if (strlen($value) > $width) {
            $value = substr($value, -$width);
        }

        return str_pad($value, $width, "\x00", STR_PAD_LEFT);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
