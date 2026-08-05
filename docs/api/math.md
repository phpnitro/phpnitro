# Package `math`

## `Engine\Math\MathUtil` (class)

The small set of numeric helpers almost every app ends up hand-rolling at least once — clamping a value into range, linear interpolation (the same "0.0-1.0 progress -> a real value" math Slider/ProgressBar's own rendering already does internally, just not exposed for a developer's own use), remapping a value from one range to another, and random numbers in a range (mt_rand()-based, NOT cryptographically secure — see Engine\Format\Format's own docblock for the same "pure PHP, no assumptions about what's compiled into the on-device PHP binary" reasoning; use random_int() directly instead if a value ever needs to be unpredictable for security reasons, e.g. a token).

### `static clamp(float $value, float $min, float $max): float`

### `static clampInt(int $value, int $min, int $max): int`

### `static lerp(float $from, float $to, float $t): float`

$t is typically 0.0-1.0 but isn't clamped here — pass clamp($t, 0, 1) yourself first if overshoot must never happen.

### `static inverseLerp(float $from, float $to, float $value): float`

Inverse of lerp() — given a value that came from lerp($from, $to, ?), recovers the t that produced it.

### `static map(float $value, float $fromMin, float $fromMax, float $toMin, float $toMax): float`

Remaps $value from [$fromMin, $fromMax] into [$toMin, $toMax] — inverseLerp() then lerp() in one call.

### `static roundTo(float $value, int $decimals = 0): float`

### `static roundToStep(float $value, float $step): float`

Rounds to the nearest multiple of $step (e.g. roundToStep(23, 5) === 25.0).

### `static isBetween(float $value, float $min, float $max): bool`

### `static percentageOf(float $value, float $total): float`

What percentage $value is of $total — percentageOf(30, 200) === 15.0. Returns 0.0 for a zero/negative $total rather than dividing by zero.

### `static randomInt(int $min, int $max): int`

Inclusive on both ends — randomInt(1, 6) can return 6. Not cryptographically secure, see this class's own docblock.

### `static randomFloat(float $min, float $max): float`

Inclusive on both ends. Not cryptographically secure, see this class's own docblock.

## `Engine\Math\Stats` (class)

Basic descriptive statistics over a plain array of numbers — dashboard report screens (average order value, median response time, that kind of thing) are the expected caller, not scientific computing; nothing here is more sophisticated than what fits in a few lines each.

### `static sum(array $values): float`

### `static mean(array $values): float`

### `static median(array $values): float`

### `static mode(array $values): array`

The most frequent value(s) — more than one when there's a tie, same convention statistics generally uses for multimodal data rather than arbitrarily picking one.

### `static min(array $values): float`

### `static max(array $values): float`

### `static range(array $values): float`

### `static variance(array $values, bool $sample = false): float`

Population variance by default ($sample = false) — pass true for sample variance (Bessel's correction, dividing by n-1 instead of n), the usual choice when $values is a sample of a larger population rather than the entire population itself.

### `static standardDeviation(array $values, bool $sample = false): float`
