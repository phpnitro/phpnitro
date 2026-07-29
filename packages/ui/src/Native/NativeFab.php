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
 * The native-tree equivalent of Engine\FloatingActionButton — meant to be
 * handed to NativeScaffold, which pins it above the bottom-right corner
 * (above NativeBottomNavigation if one is present) via RenderFixed.
 */
final class NativeFab implements RenderNode
{
    public const SIZE = 56.0;

    private readonly RenderNode $content;

    public function __construct(string $icon, string $action, ?Color $background = null)
    {
        $circle = new RenderContainer(
            new RenderCenter(new RenderIcon($icon, 24.0, Color::white()->toHex())),
            width: self::SIZE,
            height: self::SIZE,
            radius: self::SIZE / 2,
            background: $background ?? Tokens::ink(),
            elevation: 6.0,
        );

        $this->content = new RenderTappable($circle, $action);
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
