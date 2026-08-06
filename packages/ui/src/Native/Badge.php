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
 * A small count/status marker, meant to overlay a corner of whatever it's
 * paired with (an icon, an avatar) via Stack + Positioned — see
 * NativeWidgetsFormsScreen.php or wherever this gets used for the
 * pairing, this class only draws the badge itself. $count === null (or
 * 0) draws a plain dot instead of a number — the "read/unread" indicator
 * shape, not "there are 0 of something".
 */
final class Badge implements Widget
{
    private readonly Widget $content;

    public function __construct(?int $count = null, ?Color $background = null, int $max = 99)
    {
        $accent = $background ?? Tokens::danger();

        if ($count === null || $count <= 0) {
            $this->content = new Container(width: 10.0, height: 10.0, background: $accent, radius: 5.0);

            return;
        }

        $label = $count > $max ? "{$max}+" : (string) $count;
        $size = mb_strlen($label) > 1 ? 20.0 : 18.0;

        $this->content = new Container(
            new Center(new Text($label, 11.0, Color::white()->toHex(), bold: true)),
            width: mb_strlen($label) > 1 ? $size + 6.0 : $size,
            height: $size,
            background: $accent,
            radius: Tokens::RADIUS_PILL,
        );
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
