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
 * The native-tree equivalent of Engine\Checkbox — the toggled value is
 * decided server-side (the opposite of $checked) and travels in the hit
 * region's meta as "next", so a tap can flip it with no client-side
 * boolean state of its own; NativeRenderPocActivity's generic "toggle:"
 * handler (shared with Toggle) just writes meta.next into
 * fieldValues and refetches.
 */
final class Checkbox implements Widget
{
    private readonly Widget $content;

    public function __construct(string $name, string $label, bool $checked = false, ?Color $accentColor = null, float $size = 22.0)
    {
        $accent = $accentColor ?? Tokens::ink();

        $box = $checked
            ? new Container(new Center(new Icon('check', $size * 0.7, Color::white()->toHex())), width: $size, height: $size, background: $accent, radius: 6.0)
            : new Container(width: $size, height: $size, radius: 6.0, borderColor: Tokens::border(), borderWidth: 1.5);

        $row = Flex::row([
            $box,
            new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), new Text($label, Tokens::TEXT_BODY, Tokens::ink()->toHex())),
        ], crossAxisAlignment: CrossAxisAlignment::CENTER);

        $this->content = new Tappable($row, "toggle:{$name}", ['next' => $checked ? '' : '1']);
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
