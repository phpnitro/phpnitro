<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Native;

final class Text implements Widget
{
    /**
     * @var array<int, string>
     */
    private array $lines = [];

    public function __construct(
        private readonly string $text,
        private readonly float $fontSize = 16.0,
        private readonly string $color = '#000000',
        private readonly bool $bold = false,
        private readonly float $letterSpacing = 0.0,
    ) {
    }

    public function layout(Constraints $constraints): Size
    {
        $maxWidth = $constraints->hasBoundedWidth() ? $constraints->maxWidth : TextMetrics::width($this->text, $this->fontSize, $this->letterSpacing, $this->bold);
        $this->lines = TextMetrics::wrap($this->text, $this->fontSize, $maxWidth, $this->letterSpacing, $this->bold);

        $width = 0.0;
        foreach ($this->lines as $line) {
            $width = max($width, TextMetrics::width($line, $this->fontSize, $this->letterSpacing, $this->bold));
        }

        $height = count($this->lines) * TextMetrics::lineHeight($this->fontSize);

        return $constraints->constrain(new Size($width, $height));
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $lineHeight = TextMetrics::lineHeight($this->fontSize);
        // Canvas.drawText's y is the text baseline, not the top of the glyph
        // box — offsetting by ~80% of the line height approximates where
        // the baseline sits for Roboto at this size, keeping the text
        // vertically inside the box layout computed instead of hanging
        // above it.
        $baselineOffset = $this->fontSize * 0.8;

        foreach ($this->lines as $index => $line) {
            $canvas->text($x, $y + $index * $lineHeight + $baselineOffset, $line, $this->color, $this->fontSize, $this->bold, $this->letterSpacing);
        }
    }
}
