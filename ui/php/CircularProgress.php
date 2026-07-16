<?php

namespace Engine;

/**
 * Circular progress indicator (plain SVG + stroke-dasharray, no JS/canvas).
 * $value (0-100) is computed server-side, same live-update pattern as
 * ProgressBar.
 */
final class CircularProgress extends Widget
{
    public function __construct(
        private readonly float $value,
        private readonly int $size = 64,
        private readonly string $trackColor = 'text-gray-200 dark:text-gray-700',
        private readonly string $color = '#2563EB',
    ) {
    }

    public static function make(
        float $value,
        int $size = 64,
        string $trackColor = 'text-gray-200 dark:text-gray-700',
        string $color = '#2563EB',
    ): self {
        return new self($value, $size, $trackColor, $color);
    }

    public function render(): string
    {
        $percent = max(0.0, min(100.0, $this->value));
        $strokeWidth = max(2, (int) round($this->size / 16));
        $radius = ($this->size / 2) - $strokeWidth;
        $circumference = 2 * M_PI * $radius;
        $offset = $circumference * (1 - $percent / 100);
        $center = $this->size / 2;

        return sprintf(
            '<svg width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d" class="-rotate-90" '
            . 'role="progressbar" aria-valuenow="%7$s" aria-valuemin="0" aria-valuemax="100">'
            . '<circle cx="%2$s" cy="%2$s" r="%3$s" fill="none" stroke="currentColor" '
            . 'stroke-width="%4$d" class="%5$s"/>'
            . '<circle cx="%2$s" cy="%2$s" r="%3$s" fill="none" stroke="%6$s" stroke-width="%4$d" '
            . 'stroke-dasharray="%8$s" stroke-dashoffset="%9$s" stroke-linecap="round"/>'
            . '</svg>',
            $this->size,
            $center,
            $radius,
            $strokeWidth,
            htmlspecialchars($this->trackColor, ENT_QUOTES),
            htmlspecialchars($this->color, ENT_QUOTES),
            $percent,
            $circumference,
            $offset,
        );
    }
}
