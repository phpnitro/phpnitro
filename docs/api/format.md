# Package `format`

## `Engine\Format\Format` (class)

intl equivalent for the handful of things most apps actually need (number/currency/date formatting) — deliberately NOT built on PHP's ext-intl (NumberFormatter/IntlDateFormatter): whether that extension is compiled into the cross-compiled PHP binary bundled for Android (android/README.md's php-ndk recipe) is unverified, and a formatting helper that silently fails to load on-device would be worse than one with a smaller, dependency-free feature set. Pure PHP, no ICU data file.

### `static number(float $value, int $decimals = 0, array $symbols = array (
)): string`

### `static currency(float $value, string $currencyCode, array $symbols = array (
)): string`

### `static date(DateTimeInterface $date, string $pattern = 'd MMMM yyyy', string $locale = 'fr'): string`

$locale only selects month/weekday names (not a full ICU calendar) — 'fr' and 'en' ship built in; anything else falls back to 'en'.

### `static relativeTime(DateTimeInterface $date, ?DateTimeInterface $now = NULL, string $locale = 'fr'): string`

"il y a 3 heures" / "dans 2 jours" style relative time — the other common intl building block alongside absolute date formatting.
