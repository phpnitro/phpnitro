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
 * Exact decimal arithmetic via bcmath — MathUtil's clamp()/lerp()/etc.
 * are all float-based, which is the wrong tool for money (0.1 + 0.2 !==
 * 0.3 in binary floating point). This wraps bcadd/bcsub/bcmul/bcdiv/bccomp
 * behind an immutable value object instead of remembering bcmath's
 * string-based, argument-order-sensitive calling convention at every
 * call site.
 */
final class Decimal
{
    private function __construct(
        private readonly string $value,
        private readonly int $scale,
    ) {
    }

    public static function of(int|float|string $value, int $scale = 2): self
    {
        return new self(self::normalize($value, $scale), $scale);
    }

    public function add(self|int|float|string $other): self
    {
        return new self(bcadd($this->value, self::asString($other, $this->scale), $this->scale), $this->scale);
    }

    public function sub(self|int|float|string $other): self
    {
        return new self(bcsub($this->value, self::asString($other, $this->scale), $this->scale), $this->scale);
    }

    public function mul(self|int|float|string $other): self
    {
        return new self(bcmul($this->value, self::asString($other, $this->scale), $this->scale), $this->scale);
    }

    public function div(self|int|float|string $other): self
    {
        return new self(bcdiv($this->value, self::asString($other, $this->scale), $this->scale), $this->scale);
    }

    public function compareTo(self|int|float|string $other): int
    {
        return bccomp($this->value, self::asString($other, $this->scale), $this->scale);
    }

    public function equals(self|int|float|string $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function greaterThan(self|int|float|string $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function lessThan(self|int|float|string $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function toFloat(): float
    {
        return (float) $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function asString(self|int|float|string $value, int $scale): string
    {
        return $value instanceof self ? $value->value : self::normalize($value, $scale);
    }

    private static function normalize(int|float|string $value, int $scale): string
    {
        return is_float($value) ? number_format($value, $scale, '.', '') : (string) $value;
    }
}
