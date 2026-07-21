<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine;

/**
 * Typed Tailwind color (name + shade), e.g. Color::blue(600). An escape
 * hatch for anything this doesn't cover: pass a raw Tailwind class string
 * via the widget's $classes parameter instead.
 */
final class Color
{
    private function __construct(
        public readonly string $name,
        public readonly int $shade,
    ) {
    }

    public static function of(string $name, int $shade): self
    {
        return new self($name, $shade);
    }

    public static function gray(int $shade): self
    {
        return self::of('gray', $shade);
    }

    public static function blue(int $shade): self
    {
        return self::of('blue', $shade);
    }

    public static function red(int $shade): self
    {
        return self::of('red', $shade);
    }

    public static function green(int $shade): self
    {
        return self::of('green', $shade);
    }

    public function textClass(): string
    {
        return "text-{$this->name}-{$this->shade}";
    }

    public function backgroundClass(): string
    {
        return "bg-{$this->name}-{$this->shade}";
    }
}
