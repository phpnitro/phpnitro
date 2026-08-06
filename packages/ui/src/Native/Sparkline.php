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
 * A tiny inline line chart — chosen as the REFERENCE example for
 * Canvas::custom() precisely because it's a real, useful widget that
 * still has no business being a built-in engine primitive (unlike
 * ProgressBar/Slider, which every app needs). NativeCanvasView.kt has no
 * drawSparklineCommand() of its own; NativeRenderPocActivity registers
 * the actual drawing via registerCustomCommandHandler("sparkline", ...) —
 * proof the extension point works end to end without touching the
 * engine module itself, not a special case baked into it.
 */
final class Sparkline implements Widget
{
    private Size $size;

    /** @param float[] $values */
    public function __construct(
        private readonly array $values,
        private readonly float $width,
        private readonly float $height,
        private readonly string $color = '#F97316',
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
        $canvas->custom('sparkline', [
            'x' => $x,
            'y' => $y,
            'width' => $this->size->width,
            'height' => $this->size->height,
            'values' => array_values($this->values),
            'color' => $this->color,
        ]);
    }
}
