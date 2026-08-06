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
 * An icon centered in a colored circle — the single most repeated shape
 * across every native screen so far (back buttons, avatar badges, list
 * row leading/trailing icons). Optionally tappable: pass $action and the
 * whole circle becomes a hit region, same as wrapping it in
 * Tappable by hand.
 */
final class IconCircle implements Widget
{
    private readonly Widget $content;

    /**
     * @param ?array<string, mixed> $meta Extra data the client needs to handle this action —
     *                                    see Tappable's docblock.
     */
    public function __construct(
        string $icon,
        float $diameter = 40.0,
        ?Color $background = null,
        ?Color $iconColor = null,
        ?string $action = null,
        ?array $meta = null,
    ) {
        $circle = new Container(
            new Center(new Icon($icon, $diameter * 0.5, ($iconColor ?? Tokens::ink())->toHex())),
            width: $diameter,
            height: $diameter,
            radius: $diameter / 2,
            background: $background ?? Tokens::surfaceMuted(),
        );

        $this->content = $action !== null ? new Tappable($circle, $action, $meta) : $circle;
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
