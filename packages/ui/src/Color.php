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

    public static function slate(int $shade): self
    {
        return self::of('slate', $shade);
    }

    public static function indigo(int $shade): self
    {
        return self::of('indigo', $shade);
    }

    public static function amber(int $shade): self
    {
        return self::of('amber', $shade);
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
     * class's named factories; extend this table if Color::of() grows more
     * named colors.
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
            'slate' => [50 => '#F8FAFC', 100 => '#F1F5F9', 200 => '#E2E8F0', 300 => '#CBD5E1', 400 => '#94A3B8', 500 => '#64748B', 600 => '#475569', 700 => '#334155', 800 => '#1E293B', 900 => '#0F172A'],
            'indigo' => [50 => '#EEF2FF', 100 => '#E0E7FF', 200 => '#C7D2FE', 300 => '#A5B4FC', 400 => '#818CF8', 500 => '#6366F1', 600 => '#4F46E5', 700 => '#4338CA', 800 => '#3730A3', 900 => '#312E81'],
            'amber' => [50 => '#FFFBEB', 100 => '#FEF3C7', 200 => '#FDE68A', 300 => '#FCD34D', 400 => '#FBBF24', 500 => '#F59E0B', 600 => '#D97706', 700 => '#B45309', 800 => '#92400E', 900 => '#78350F'],
            'purple' => [50 => '#FAF5FF', 100 => '#F3E8FF', 200 => '#E9D5FF', 300 => '#D8B4FE', 400 => '#C084FC', 500 => '#A855F7', 600 => '#9333EA', 700 => '#7E22CE', 800 => '#6B21A8', 900 => '#581C87'],
            'emerald' => [50 => '#ECFDF5', 100 => '#D1FAE5', 200 => '#A7F3D0', 300 => '#6EE7B7', 400 => '#34D399', 500 => '#10B981', 600 => '#059669', 700 => '#047857', 800 => '#065F46', 900 => '#064E3B'],
            'yellow' => [50 => '#FEFCE8', 100 => '#FEF9C3', 200 => '#FEF08A', 300 => '#FDE047', 400 => '#FACC15', 500 => '#EAB308', 600 => '#CA8A04', 700 => '#A16207', 800 => '#854D0E', 900 => '#713F12'],
            'orange' => [50 => '#FFF7ED', 100 => '#FFEDD5', 200 => '#FED7AA', 300 => '#FDBA74', 400 => '#FB923C', 500 => '#F97316', 600 => '#EA580C', 700 => '#C2410C', 800 => '#9A3412', 900 => '#7C2D12'],
        ];

        return $palette[$this->name][$this->shade] ?? '#000000';
    }
}
