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
final class NativeDivider implements RenderNode
{
    private readonly RenderContainer $content;

    public function __construct(float $thickness = 1.0)
    {
        $this->content = new RenderContainer(height: $thickness, background: Tokens::border());
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->content->layout($constraints);
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $this->content->paint($canvas, $x, $y);
    }
}
