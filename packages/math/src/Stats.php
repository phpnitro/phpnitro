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
 * Basic descriptive statistics over a plain array of numbers — dashboard/
 * report screens (average order value, median response time, that kind
 * of thing) are the expected caller, not scientific computing; nothing
 * here is more sophisticated than what fits in a few lines each.
 */
final class Stats
{
    /** @param float[] $values */
    public static function sum(array $values): float
    {
        return array_sum($values);
    }

    /** @param float[] $values */
    public static function mean(array $values): float
    {
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    /** @param float[] $values */
    public static function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2.0
            : $values[$middle];
    }

    /**
     * The most frequent value(s) — more than one when there's a tie,
     * same convention statistics generally uses for multimodal data
     * rather than arbitrarily picking one.
     *
     * @param float[] $values
     * @return float[]
     */
    public static function mode(array $values): array
    {
        if ($values === []) {
            return [];
        }

        $counts = [];
        foreach ($values as $value) {
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        $maxCount = max($counts);

        return array_map(
            static fn (string $value): float => (float) $value,
            array_keys(array_filter($counts, static fn (int $count): bool => $count === $maxCount)),
        );
    }

    /** @param float[] $values */
    public static function min(array $values): float
    {
        return $values === [] ? 0.0 : min($values);
    }

    /** @param float[] $values */
    public static function max(array $values): float
    {
        return $values === [] ? 0.0 : max($values);
    }

    /** @param float[] $values */
    public static function range(array $values): float
    {
        return $values === [] ? 0.0 : self::max($values) - self::min($values);
    }

    /**
     * Population variance by default ($sample = false) — pass true for
     * sample variance (Bessel's correction, dividing by n-1 instead of
     * n), the usual choice when $values is a sample of a larger
     * population rather than the entire population itself.
     *
     * @param float[] $values
     */
    public static function variance(array $values, bool $sample = false): float
    {
        $count = count($values);
        if ($count === 0 || ($sample && $count < 2)) {
            return 0.0;
        }

        $mean = self::mean($values);
        $squaredDiffs = array_map(static fn (float $v): float => ($v - $mean) ** 2, $values);
        $divisor = $sample ? $count - 1 : $count;

        return array_sum($squaredDiffs) / $divisor;
    }

    /** @param float[] $values */
    public static function standardDeviation(array $values, bool $sample = false): float
    {
        return sqrt(self::variance($values, $sample));
    }
}
