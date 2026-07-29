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
 * The one visual unit every native screen so far is built from: a
 * surface-colored box, rounded corners, a thin border by default (the
 * captures/ reference screens are flat and high-contrast, not
 * shadow-driven — pass $elevation explicitly for the earlier
 * gradient/shadow style instead). Formalizes what used to be an inline
 * closure duplicated across NativeDocumentsScreen/NativeSettingsScreen.
 */
final class NativeCard implements RenderNode
{
    private readonly RenderContainer $content;

    public function __construct(
        RenderNode $child,
        ?EdgeInsets $padding = null,
        ?Color $background = null,
        ?Color $borderColor = null,
        float $borderWidth = 1.0,
        float $radius = Tokens::RADIUS_LG,
        float $elevation = 0.0,
    ) {
        $this->content = new RenderContainer(
            new RenderPadding($padding ?? EdgeInsets::all(Tokens::SPACE_LG), $child),
            background: $background ?? Tokens::surface(),
            radius: $radius,
            borderColor: $elevation > 0.0 ? null : ($borderColor ?? Tokens::border()),
            borderWidth: $borderWidth,
            elevation: $elevation,
        );
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
