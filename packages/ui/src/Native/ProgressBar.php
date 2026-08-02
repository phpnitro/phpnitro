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
 * A track + a proportionally-sized fill, both pill-rounded — takes an
 * explicit pixel width rather than stretching to its parent, since a
 * Stack (used here to overlay the fill on the track) doesn't resolve
 * percentage widths on its own; call sites already know their available
 * width the same way Button's do.
 */
final class ProgressBar implements Widget
{
    private readonly Stack $content;

    public function __construct(float $width, float $percent, float $height = 8.0, ?Color $trackColor = null, ?Color $fillColor = null)
    {
        $clamped = max(0.0, min(1.0, $percent));

        $this->content = new Stack([
            new Container(width: $width, height: $height, background: $trackColor ?? Tokens::surfaceMuted(), radius: $height / 2),
            new Container(width: $width * $clamped, height: $height, background: $fillColor ?? Tokens::ink(), radius: $height / 2),
        ]);
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->content->layout($constraints);
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $this->content->paint($canvas, $x, $y);
    }
}
