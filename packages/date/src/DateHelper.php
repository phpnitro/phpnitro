<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Date;

/**
 * Date arithmetic + the one thing PHP's own DateTime/DateInterval make
 * genuinely annoying to get right: a relative "time ago" string ("il y a
 * 3 jours"). Built on \DateTimeImmutable throughout — every method that
 * takes a date accepts either a real DateTimeImmutable or an ISO-8601
 * string (DATE_ATOM, the same format every Repository in this framework
 * already stores timestamps as — UserRepository::create(),
 * VisitRepository::recordVisit(), etc.), so a raw DB row's string column
 * can be handed straight in without the caller parsing it first.
 */
final class DateHelper
{
    public static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    public static function parse(\DateTimeImmutable|string $date): \DateTimeImmutable
    {
        return is_string($date) ? new \DateTimeImmutable($date) : $date;
    }

    public static function toIso(\DateTimeImmutable|string $date): string
    {
        return self::parse($date)->format(DATE_ATOM);
    }

    public static function addDays(\DateTimeImmutable|string $date, int $days): \DateTimeImmutable
    {
        return self::parse($date)->modify(($days >= 0 ? '+' : '') . $days . ' days');
    }

    public static function addHours(\DateTimeImmutable|string $date, int $hours): \DateTimeImmutable
    {
        return self::parse($date)->modify(($hours >= 0 ? '+' : '') . $hours . ' hours');
    }

    public static function addMinutes(\DateTimeImmutable|string $date, int $minutes): \DateTimeImmutable
    {
        return self::parse($date)->modify(($minutes >= 0 ? '+' : '') . $minutes . ' minutes');
    }

    public static function diffInSeconds(\DateTimeImmutable|string $from, \DateTimeImmutable|string $to): int
    {
        return self::parse($to)->getTimestamp() - self::parse($from)->getTimestamp();
    }

    public static function diffInMinutes(\DateTimeImmutable|string $from, \DateTimeImmutable|string $to): int
    {
        return intdiv(self::diffInSeconds($from, $to), 60);
    }

    public static function diffInHours(\DateTimeImmutable|string $from, \DateTimeImmutable|string $to): int
    {
        return intdiv(self::diffInSeconds($from, $to), 3600);
    }

    public static function diffInDays(\DateTimeImmutable|string $from, \DateTimeImmutable|string $to): int
    {
        return intdiv(self::diffInSeconds($from, $to), 86400);
    }

    public static function isPast(\DateTimeImmutable|string $date): bool
    {
        return self::parse($date) < self::now();
    }

    public static function isFuture(\DateTimeImmutable|string $date): bool
    {
        return self::parse($date) > self::now();
    }

    public static function isToday(\DateTimeImmutable|string $date): bool
    {
        return self::parse($date)->format('Y-m-d') === self::now()->format('Y-m-d');
    }

    public static function isWeekend(\DateTimeImmutable|string $date): bool
    {
        return (int) self::parse($date)->format('N') >= 6; // ISO-8601: 6 = Saturday, 7 = Sunday
    }

    public static function startOfDay(\DateTimeImmutable|string $date): \DateTimeImmutable
    {
        return self::parse($date)->setTime(0, 0, 0);
    }

    public static function endOfDay(\DateTimeImmutable|string $date): \DateTimeImmutable
    {
        return self::parse($date)->setTime(23, 59, 59);
    }

    public static function startOfMonth(\DateTimeImmutable|string $date): \DateTimeImmutable
    {
        return self::parse($date)->modify('first day of this month')->setTime(0, 0, 0);
    }

    public static function endOfMonth(\DateTimeImmutable|string $date): \DateTimeImmutable
    {
        return self::parse($date)->modify('last day of this month')->setTime(23, 59, 59);
    }

    /** Whole years elapsed since $birthDate, as of $now (defaults to the real current time) — the usual "age" definition, not a raw day-count division. */
    public static function age(\DateTimeImmutable|string $birthDate, \DateTimeImmutable|string|null $now = null): int
    {
        $reference = $now === null ? self::now() : self::parse($now);

        return self::parse($birthDate)->diff($reference)->y;
    }

    /**
     * "à l'instant" / "il y a 5 minutes" / "dans 2 heures" — French,
     * matching every other user-facing string in this framework's own
     * demo screens. Compares against $now (defaults to the real current
     * time — overridable for deterministic tests) rather than always
     * using time() directly, so a caller (or a test) can pin "now" to an
     * exact instant instead of racing the clock.
     */
    public static function humanize(\DateTimeImmutable|string $date, \DateTimeImmutable|string|null $now = null): string
    {
        $reference = $now === null ? self::now() : self::parse($now);
        $target = self::parse($date);
        $seconds = $reference->getTimestamp() - $target->getTimestamp();
        $isPast = $seconds >= 0;
        $seconds = abs($seconds);

        if ($seconds < 10) {
            return "à l'instant";
        }

        [$amount, $unit] = match (true) {
            $seconds < 60 => [$seconds, 'seconde'],
            $seconds < 3600 => [intdiv($seconds, 60), 'minute'],
            $seconds < 86400 => [intdiv($seconds, 3600), 'heure'],
            $seconds < 2592000 => [intdiv($seconds, 86400), 'jour'],
            $seconds < 31536000 => [intdiv($seconds, 2592000), 'mois'],
            default => [intdiv($seconds, 31536000), 'an'],
        };

        // "mois" is already both singular and plural in French; every
        // other unit here just takes a trailing "s".
        $plural = $amount > 1 && $unit !== 'mois' ? 's' : '';
        $label = "{$amount} {$unit}{$plural}";

        return $isPast ? "il y a {$label}" : "dans {$label}";
    }
}
