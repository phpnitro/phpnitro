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

/**
 * A minimal pie chart — same Canvas::custom() pattern as Sparkline/
 * BarChart (see Sparkline's own docblock), drawn by
 * NativeRenderPocActivity's registerCustomCommandHandlers(), not a new
 * engine primitive.
 *
 * $colors is optional and cycles through DEFAULT_PALETTE for any slice
 * past its end — a pie chart is meaningless with one flat color the way
 * Sparkline/BarChart can get away with, but hand-picking every slice's
 * color for a quick chart shouldn't be mandatory either.
 */
final class PieChart implements Widget
{
    private const DEFAULT_PALETTE = ['#2563EB', '#F97316', '#16A34A', '#DC2626', '#7C3AED', '#0891B2', '#CA8A04'];

    private Size $size;

    /**
     * @param float[] $values
     * @param string[] $colors
     */
    public function __construct(
        private readonly array $values,
        private readonly float $diameter,
        private readonly array $colors = [],
    ) {
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $this->size = $constraints->constrain(new Size($this->diameter, $this->diameter));

        return $this->size;
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $values = array_values($this->values);
        $resolvedColors = [];
        foreach ($values as $index => $_) {
            $resolvedColors[] = $this->colors[$index] ?? self::DEFAULT_PALETTE[$index % count(self::DEFAULT_PALETTE)];
        }

        $canvas->custom('pieChart', [
            'x' => $x,
            'y' => $y,
            'diameter' => $this->size->width,
            'values' => $values,
            'colors' => $resolvedColors,
        ]);
    }
}
