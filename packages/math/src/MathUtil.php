<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Math;

/**
 * The small set of numeric helpers almost every app ends up hand-rolling
 * at least once — clamping a value into range, linear interpolation
 * (the same "0.0-1.0 progress -> a real value" math Slider/ProgressBar's
 * own rendering already does internally, just not exposed for a
 * developer's own use), remapping a value from one range to another, and
 * random numbers in a range (mt_rand()-based, NOT cryptographically
 * secure — see Engine\Format\Format's own docblock for the same "pure
 * PHP, no assumptions about what's compiled into the on-device PHP
 * binary" reasoning; use random_int() directly instead if a value ever
 * needs to be unpredictable for security reasons, e.g. a token).
 */
final class MathUtil
{
    public static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    public static function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    /** $t is typically 0.0-1.0 but isn't clamped here — pass clamp($t, 0, 1) yourself first if overshoot must never happen. */
    public static function lerp(float $from, float $to, float $t): float
    {
        return $from + ($to - $from) * $t;
    }

    /** Inverse of lerp() — given a value that came from lerp($from, $to, ?), recovers the t that produced it. */
    public static function inverseLerp(float $from, float $to, float $value): float
    {
        if ($from === $to) {
            return 0.0;
        }

        return ($value - $from) / ($to - $from);
    }

    /** Remaps $value from [$fromMin, $fromMax] into [$toMin, $toMax] — inverseLerp() then lerp() in one call. */
    public static function map(float $value, float $fromMin, float $fromMax, float $toMin, float $toMax): float
    {
        return self::lerp($toMin, $toMax, self::inverseLerp($fromMin, $fromMax, $value));
    }

    public static function roundTo(float $value, int $decimals = 0): float
    {
        return round($value, $decimals);
    }

    /** Rounds to the nearest multiple of $step (e.g. roundToStep(23, 5) === 25.0). */
    public static function roundToStep(float $value, float $step): float
    {
        if ($step <= 0.0) {
            return $value;
        }

        return round($value / $step) * $step;
    }

    public static function isBetween(float $value, float $min, float $max): bool
    {
        return $value >= $min && $value <= $max;
    }

    /** What percentage $value is of $total — percentageOf(30, 200) === 15.0. Returns 0.0 for a zero/negative $total rather than dividing by zero. */
    public static function percentageOf(float $value, float $total): float
    {
        return $total <= 0.0 ? 0.0 : ($value / $total) * 100.0;
    }

    /** Inclusive on both ends — randomInt(1, 6) can return 6. Not cryptographically secure, see this class's own docblock. */
    public static function randomInt(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    /** Inclusive on both ends. Not cryptographically secure, see this class's own docblock. */
    public static function randomFloat(float $min, float $max): float
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }
}
