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
 * Decodes a JWT's header/payload — base64url + JSON, no library needed
 * for that part. Deliberately does NOT verify the signature (same scope
 * Flutter's jwt_decoder package takes): this is for reading claims out of
 * a token your own backend or an OAuth provider already issued/verified
 * (see Engine\SocialAuth\OAuthProvider), not for trusting an
 * arbitrary token's claims as authenticated. Verifying a signature needs
 * the actual algorithm + key/cert, which belongs to whoever issued the
 * token, not a generic decoder.
 */
final class JwtDecoder
{
    /**
     * @return array{header: array<string, mixed>, payload: array<string, mixed>}
     */
    public static function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) < 2) {
            throw new \InvalidArgumentException('Jeton JWT invalide : format inattendu.');
        }

        return [
            'header' => self::decodeSegment($parts[0]),
            'payload' => self::decodeSegment($parts[1]),
        ];
    }

    /** @return array<string, mixed> */
    public static function payload(string $token): array
    {
        return self::decode($token)['payload'];
    }

    /** True when the token has an `exp` claim in the past. A token with no `exp` claim is never considered expired. */
    public static function isExpired(string $token): bool
    {
        $exp = self::payload($token)['exp'] ?? null;

        return $exp !== null && (int) $exp < time();
    }

    /** @return array<string, mixed> */
    private static function decodeSegment(string $segment): array
    {
        $segment = strtr($segment, '-_', '+/');
        $padding = 4 - (strlen($segment) % 4);
        if ($padding < 4) {
            $segment .= str_repeat('=', $padding);
        }

        $decoded = base64_decode($segment, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Jeton JWT invalide : segment base64url illisible.');
        }

        $data = json_decode($decoded, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Jeton JWT invalide : segment JSON illisible.');
        }

        return $data;
    }
}
