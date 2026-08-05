<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Crypto;

/**
 * Thin, discoverable names over PHP's own hash()/hash_hmac()/random_bytes()
 * — the functions this wraps already exist, the value here is the same
 * one Flutter's crypto package provides: Crypto::sha256($x) reads better
 * at a call site than remembering hash()'s algorithm-name-as-string
 * calling convention, and randomToken() saves reaching for bin2hex()
 * every time a token is needed.
 */
final class Crypto
{
    public static function sha256(string $data): string
    {
        return hash('sha256', $data);
    }

    public static function sha1(string $data): string
    {
        return hash('sha1', $data);
    }

    public static function md5(string $data): string
    {
        return hash('md5', $data);
    }

    public static function hmacSha256(string $data, string $key): string
    {
        return hash_hmac('sha256', $data, $key);
    }

    /** Timing-safe — use this to check an HMAC, never `===`. */
    public static function verifyHmac(string $data, string $key, string $signature): bool
    {
        return hash_equals(self::hmacSha256($data, $key), $signature);
    }

    /** Hex-encoded cryptographically secure random token, $bytes long before encoding (so the string is twice this length). */
    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
