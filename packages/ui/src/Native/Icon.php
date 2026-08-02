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
 * A fixed-size square icon, drawn from the real Material Icons font
 * (2235 names — anything at https://fonts.google.com/icons, e.g.
 * 'arrow_back', 'shopping_cart', 'notifications', 'settings'). See
 * MaterialIcons::codepoint() for the name -> glyph lookup and
 * NativeCanvasView.kt for how the glyph actually gets painted.
 */
final class Icon implements Widget
{
    private readonly int $codepoint;

    public function __construct(
        string $name,
        private readonly float $size = 24.0,
        private readonly string $color = '#111827',
    ) {
        $this->codepoint = MaterialIcons::codepoint($name);
    }

    public function layout(Constraints $constraints): Size
    {
        return $constraints->constrain(new Size($this->size, $this->size));
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $canvas->icon($x, $y, $this->size, $this->codepoint, $this->color);
    }
}
