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
 * A fixed-size square icon, drawn from a real icon font — Material Icons
 * by default (2235 names — anything at https://fonts.google.com/icons,
 * e.g. 'arrow_back', 'shopping_cart', 'notifications', 'settings'), or
 * Font Awesome Solid with $font: 'fontawesome' (1392 names — anything at
 * https://fontawesome.com/search?o=r&s=solid, dashes become underscores,
 * e.g. 'chart_line', 'user_group'). See MaterialIcons::codepoint()/
 * FontAwesomeIcons::codepoint() for the name -> glyph lookup and
 * NativeCanvasView.kt for how the glyph actually gets painted.
 */
final class Icon implements Widget
{
    private readonly int $codepoint;

    public function __construct(
        string $name,
        private readonly float $size = 24.0,
        private readonly string $color = '#111827',
        private readonly string $font = 'material',
    ) {
        $this->codepoint = $this->font === 'fontawesome'
            ? FontAwesomeIcons::codepoint($name)
            : MaterialIcons::codepoint($name);
    }

    public function layout(Constraints $constraints): Size
    {
        return $constraints->constrain(new Size($this->size, $this->size));
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $canvas->icon($x, $y, $this->size, $this->codepoint, $this->color, $this->font);
    }
}
