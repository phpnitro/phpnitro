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
 * The native-tree equivalent of Engine\SwitchToggle — same "next" meta +
 * shared "toggle:" handler as Checkbox, just a pill track with an
 * offset knob instead of a check mark. The knob's position is drawn
 * directly from the current $on value (no animation — a fresh render
 * always reflects the true state, same one-shot-paint contract every
 * other native widget has).
 */
final class Toggle implements Widget
{
    private readonly Widget $content;

    public function __construct(string $name, string $label, bool $on = false, ?Color $activeColor = null, float $trackWidth = 44.0, float $trackHeight = 26.0)
    {
        $knobSize = $trackHeight - 4.0;
        $knob = new Positioned(
            new Container(width: $knobSize, height: $knobSize, radius: $knobSize / 2, background: Color::white()),
            top: 2.0,
            left: $on ? $trackWidth - $knobSize - 2.0 : 2.0,
        );

        $track = new Stack([
            new Container(width: $trackWidth, height: $trackHeight, radius: $trackHeight / 2, background: $on ? ($activeColor ?? Tokens::ink()) : Tokens::border()),
            $knob,
        ]);

        $row = Flex::row([
            $track,
            new Padding(EdgeInsets::only(left: Tokens::SPACE_MD), new Text($label, Tokens::TEXT_BODY, Tokens::ink()->toHex())),
        ], crossAxisAlignment: CrossAxisAlignment::CENTER);

        $this->content = new Tappable($row, "toggle:{$name}", ['next' => $on ? '' : '1']);
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
