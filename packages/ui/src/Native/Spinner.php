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

use Engine\Color;

/**
 * Flutter's CircularProgressIndicator() with no `value` — an indeterminate
 * spinner that keeps rotating on its own, unlike CircularProgress
 * which needs a real percent recomputed every render. See
 * Canvas::spinner()'s docblock for how a request/response pipeline
 * expresses "keep animating with nobody asking again."
 */
final class Spinner implements Widget
{
    private Size $size;

    public function __construct(
        private readonly float $diameter = 32.0,
        private readonly ?Color $color = null,
        private readonly ?Color $trackColor = null,
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
        $strokeWidth = max(2.0, $this->diameter / 12);
        $canvas->spinner(
            $x,
            $y,
            $this->diameter,
            ($this->color ?? Tokens::ink())->toHex(),
            ($this->trackColor ?? Tokens::surfaceMuted())->toHex(),
            $strokeWidth,
        );
    }
}
