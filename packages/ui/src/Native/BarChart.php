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
 * A minimal vertical bar chart — built the exact same way Sparkline was,
 * as a Canvas::custom() consumer rather than a new engine primitive (see
 * Sparkline's own docblock for why: a specific chart type is a real,
 * useful widget, but not one every app needs the way ProgressBar/Slider
 * are). NativeCanvasView.kt has no drawBarChartCommand() of its own —
 * NativeRenderPocActivity's registerCustomCommandHandlers() draws it,
 * right next to the sparkline handler it was modeled on.
 *
 * Deliberately no axis labels/legend baked in here — compose a Text
 * below/beside it for that, the same "small orthogonal widgets, not one
 * that tries to do everything" bet the rest of this framework makes.
 */
final class BarChart implements Widget
{
    private Size $size;

    /** @param float[] $values */
    public function __construct(
        private readonly array $values,
        private readonly float $width,
        private readonly float $height,
        private readonly string $color = '#2563EB',
        private readonly float $gap = 4.0,
    ) {
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $this->size = $constraints->constrain(new Size($this->width, $this->height));

        return $this->size;
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $canvas->custom('barChart', [
            'x' => $x,
            'y' => $y,
            'width' => $this->size->width,
            'height' => $this->size->height,
            'values' => array_values($this->values),
            'color' => $this->color,
            'gap' => $this->gap,
        ]);
    }
}
