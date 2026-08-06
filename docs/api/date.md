# Package `date`

## `Engine\Date\DateHelper` (class)

Date arithmetic + the one thing PHP's own DateTime/DateInterval make genuinely annoying to get right: a relative "time ago" string ("il y a 3 jours"). Built on \DateTimeImmutable throughout — every method that takes a date accepts either a real DateTimeImmutable or an ISO-8601 string (DATE_ATOM, the same format every Repository in this framework already stores timestamps as — UserRepository::create(), VisitRepository::recordVisit(), etc.), so a raw DB row's string column can be handed straight in without the caller parsing it first.

### `static now(): DateTimeImmutable`

### `static parse(DateTimeImmutable|string $date): DateTimeImmutable`

### `static toIso(DateTimeImmutable|string $date): string`

### `static addDays(DateTimeImmutable|string $date, int $days): DateTimeImmutable`

### `static addHours(DateTimeImmutable|string $date, int $hours): DateTimeImmutable`

### `static addMinutes(DateTimeImmutable|string $date, int $minutes): DateTimeImmutable`

### `static diffInSeconds(DateTimeImmutable|string $from, DateTimeImmutable|string $to): int`

### `static diffInMinutes(DateTimeImmutable|string $from, DateTimeImmutable|string $to): int`

### `static diffInHours(DateTimeImmutable|string $from, DateTimeImmutable|string $to): int`

### `static diffInDays(DateTimeImmutable|string $from, DateTimeImmutable|string $to): int`

### `static isPast(DateTimeImmutable|string $date): bool`

### `static isFuture(DateTimeImmutable|string $date): bool`

### `static isToday(DateTimeImmutable|string $date): bool`

### `static isWeekend(DateTimeImmutable|string $date): bool`

### `static startOfDay(DateTimeImmutable|string $date): DateTimeImmutable`

### `static endOfDay(DateTimeImmutable|string $date): DateTimeImmutable`

### `static startOfMonth(DateTimeImmutable|string $date): DateTimeImmutable`

### `static endOfMonth(DateTimeImmutable|string $date): DateTimeImmutable`

### `static age(DateTimeImmutable|string $birthDate, DateTimeImmutable|string|null $now = NULL): int`

Whole years elapsed since $birthDate, as of $now (defaults to the real current time) — the usual "age" definition, not a raw day-count division.

### `static humanize(DateTimeImmutable|string $date, DateTimeImmutable|string|null $now = NULL): string`

"à l'instant" / "il y a 5 minutes" / "dans 2 heures" — French, matching every other user-facing string in this framework's own demo screens. Compares against $now (defaults to the real current time — overridable for deterministic tests) rather than always using time() directly, so a caller (or a test) can pin "now" to an exact instant instead of racing the clock.
