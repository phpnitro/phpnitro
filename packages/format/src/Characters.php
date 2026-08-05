<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Format;

/**
 * Grapheme-cluster-aware string operations — mb_str_split()/mb_substr()
 * split by CODE POINT, which cuts a flag emoji (two regional-indicator
 * code points) or a ZWJ family emoji (several code points joined into one
 * visual character) in half. The intl extension's grapheme_* functions
 * split by what a user actually perceives as "one character" instead;
 * these fall back to the mb_* functions when intl isn't loaded (a
 * correctness downgrade for exotic emoji, not a crash).
 */
final class Characters
{
    /** @return array<int, string> */
    public static function graphemes(string $text): array
    {
        if (!function_exists('grapheme_str_split')) {
            return mb_str_split($text) ?: [];
        }

        return grapheme_str_split($text) ?: [];
    }

    public static function length(string $text): int
    {
        if (!function_exists('grapheme_strlen')) {
            return mb_strlen($text);
        }

        return grapheme_strlen($text) ?? mb_strlen($text);
    }

    public static function substring(string $text, int $start, ?int $length = null): string
    {
        if (!function_exists('grapheme_substr')) {
            return mb_substr($text, $start, $length);
        }

        return grapheme_substr($text, $start, $length) ?: '';
    }

    public static function reverse(string $text): string
    {
        return implode('', array_reverse(self::graphemes($text)));
    }
}
