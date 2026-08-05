<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Uuid;

/**
 * RFC 4122 UUIDs from PHP's own random_bytes() — no Composer dependency
 * (ramsey/uuid is the usual reach, but a v4/v7 generator is ~20 lines of
 * bit-twiddling, not worth a dependency for).
 */
final class Uuid
{
    /**
     * A fully random UUID — use this unless you specifically need v7's
     * sortability (see v7()).
     */
    public static function v4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return self::format($bytes);
    }

    /**
     * A time-ordered UUID (48-bit millisecond timestamp + 74 random
     * bits) — sorts chronologically as a plain string/bytes comparison,
     * which makes it a better database primary key than v4 (v4's full
     * randomness scatters index inserts; v7's leading timestamp keeps
     * them appending at the end, same reasoning ULIDs/Snowflake IDs use).
     */
    public static function v7(): string
    {
        $unixMs = (int) (microtime(true) * 1000);
        $timeBytes = substr(pack('J', $unixMs), 2, 6);

        $rand = random_bytes(10);
        $rand[0] = chr((ord($rand[0]) & 0x0f) | 0x70);
        $rand[2] = chr((ord($rand[2]) & 0x3f) | 0x80);

        return self::format($timeBytes . $rand);
    }

    public static function isValid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    private static function format(string $bytes): string
    {
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
