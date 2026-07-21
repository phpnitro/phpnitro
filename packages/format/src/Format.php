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
 * intl equivalent for the handful of things most apps actually need
 * (number/currency/date formatting) — deliberately NOT built on PHP's
 * ext-intl (NumberFormatter/IntlDateFormatter): whether that extension is
 * compiled into the cross-compiled PHP binary bundled for Android
 * (android/README.md's php-ndk recipe) is unverified, and a formatting
 * helper that silently fails to load on-device would be worse than one
 * with a smaller, dependency-free feature set. Pure PHP, no ICU data file.
 */
final class Format
{
    /**
     * @param array<string, string> $symbols e.g. ['decimal' => ',', 'thousands' => ' ']
     */
    public static function number(float $value, int $decimals = 0, array $symbols = []): string
    {
        $decimalSeparator = $symbols['decimal'] ?? ',';
        $thousandsSeparator = $symbols['thousands'] ?? ' ';

        return number_format($value, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    /**
     * @param array<string, string> $symbols
     */
    public static function currency(float $value, string $currencyCode, array $symbols = []): string
    {
        $number = self::number($value, 2, $symbols);
        $symbol = self::CURRENCY_SYMBOLS[$currencyCode] ?? $currencyCode;

        return "{$number} {$symbol}";
    }

    private const CURRENCY_SYMBOLS = [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
        'XOF' => 'FCFA',
        'XAF' => 'FCFA',
        'JPY' => '¥',
        'CNY' => '¥',
        'NGN' => '₦',
        'GHS' => '₵',
    ];

    /**
     * $locale only selects month/weekday names (not a full ICU calendar) —
     * 'fr' and 'en' ship built in; anything else falls back to 'en'.
     */
    public static function date(\DateTimeInterface $date, string $pattern = 'd MMMM yyyy', string $locale = 'fr'): string
    {
        $months = self::MONTHS[$locale] ?? self::MONTHS['en'];
        $weekdays = self::WEEKDAYS[$locale] ?? self::WEEKDAYS['en'];

        $replacements = [
            'yyyy' => $date->format('Y'),
            'MMMM' => $months[(int) $date->format('n') - 1],
            'MM' => $date->format('m'),
            'EEEE' => $weekdays[(int) $date->format('w')],
            'dd' => $date->format('d'),
            'd' => (string) (int) $date->format('d'),
            'HH' => $date->format('H'),
            'mm' => $date->format('i'),
            'ss' => $date->format('s'),
        ];

        return strtr($pattern, $replacements);
    }

    private const MONTHS = [
        'fr' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    ];

    private const WEEKDAYS = [
        'fr' => ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'],
        'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    ];

    /**
     * "il y a 3 heures" / "dans 2 jours" style relative time — the other
     * common intl building block alongside absolute date formatting.
     */
    public static function relativeTime(\DateTimeInterface $date, ?\DateTimeInterface $now = null, string $locale = 'fr'): string
    {
        $now ??= new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $date->getTimestamp();
        $future = $diff < 0;
        $diff = abs($diff);

        $units = [
            ['an', 'ans', 'year', 'years', 31536000],
            ['mois', 'mois', 'month', 'months', 2592000],
            ['jour', 'jours', 'day', 'days', 86400],
            ['heure', 'heures', 'hour', 'hours', 3600],
            ['minute', 'minutes', 'minute', 'minutes', 60],
        ];

        foreach ($units as [$frSingular, $frPlural, $enSingular, $enPlural, $seconds]) {
            $count = intdiv($diff, $seconds);
            if ($count >= 1) {
                $label = $locale === 'fr'
                    ? ($count === 1 ? $frSingular : $frPlural)
                    : ($count === 1 ? $enSingular : $enPlural);

                if ($locale === 'fr') {
                    return $future ? "dans {$count} {$label}" : "il y a {$count} {$label}";
                }

                return $future ? "in {$count} {$label}" : "{$count} {$label} ago";
            }
        }

        return $locale === 'fr' ? "à l'instant" : 'just now';
    }
}
