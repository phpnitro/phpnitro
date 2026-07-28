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

    public static function white(): self
    {
        return self::of('white', 0);
    }

    public static function black(): self
    {
        return self::of('black', 0);
    }

    public function textClass(): string
    {
        return "text-{$this->name}-{$this->shade}";
    }

    public function backgroundClass(): string
    {
        return "bg-{$this->name}-{$this->shade}";
    }

    /**
     * Standard Tailwind v3 palette values, needed by the native render
     * engine (packages/ui/src/Native) which draws on a real Canvas and has
     * no CSS to hand this off to — draw commands need an actual hex color,
     * not a class name. Only covers the shades reachable through this
     * class's named factories (gray/blue/red/green); extend this table if
     * Color::of() grows more named colors.
     */
    public function toHex(): string
    {
        if ($this->name === 'white') {
            return '#FFFFFF';
        }

        if ($this->name === 'black') {
            return '#000000';
        }

        $palette = [
            'gray' => [50 => '#F9FAFB', 100 => '#F3F4F6', 200 => '#E5E7EB', 300 => '#D1D5DB', 400 => '#9CA3AF', 500 => '#6B7280', 600 => '#4B5563', 700 => '#374151', 800 => '#1F2937', 900 => '#111827'],
            'blue' => [50 => '#EFF6FF', 100 => '#DBEAFE', 200 => '#BFDBFE', 300 => '#93C5FD', 400 => '#60A5FA', 500 => '#3B82F6', 600 => '#2563EB', 700 => '#1D4ED8', 800 => '#1E40AF', 900 => '#1E3A8A'],
            'red' => [50 => '#FEF2F2', 100 => '#FEE2E2', 200 => '#FECACA', 300 => '#FCA5A5', 400 => '#F87171', 500 => '#EF4444', 600 => '#DC2626', 700 => '#B91C1C', 800 => '#991B1B', 900 => '#7F1D1D'],
            'green' => [50 => '#F0FDF4', 100 => '#DCFCE7', 200 => '#BBF7D0', 300 => '#86EFAC', 400 => '#4ADE80', 500 => '#22C55E', 600 => '#16A34A', 700 => '#15803D', 800 => '#166534', 900 => '#14532D'],
        ];

        return $palette[$this->name][$this->shade] ?? '#000000';
    }
}
