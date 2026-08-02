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
 * A 1px-tall filled bar spanning whatever width its parent gives it — the
 * native-tree equivalent of Engine\Divider (an <hr>-like Tailwind rule).
 */
final class Divider implements Widget
{
    private readonly Container $content;

    public function __construct(float $thickness = 1.0)
    {
        $this->content = new Container(height: $thickness, background: Tokens::border());
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
