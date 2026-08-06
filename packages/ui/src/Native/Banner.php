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
 * The native-tree equivalent of Engine\ErrorBanner — stays in the normal
 * layout flow (unlike Engine\FlashMessage, which is fixed-position and
 * auto-dismisses via CSS; there's no client-side timer/overlay mechanism
 * for that on this pipeline yet), so screens keep using it the same way:
 * pass the current validation error straight through, render nothing when
 * it's null/empty.
 */
final class Banner implements Widget
{
    private readonly ?Widget $content;

    public function __construct(?string $message, string $icon = 'warning', ?Color $background = null, ?Color $foreground = null)
    {
        if ($message === null || $message === '') {
            $this->content = null;

            return;
        }

        $fg = $foreground ?? Tokens::danger();

        $this->content = new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_MD),
                Flex::row([
                    new Icon($icon, 20, $fg->toHex()),
                    new Flexible(new Padding(
                        EdgeInsets::only(left: Tokens::SPACE_SM),
                        new Text($message, Tokens::TEXT_BODY_SMALL, $fg->toHex()),
                    )),
                ]),
            ),
            background: $background ?? Tokens::dangerMuted(),
            radius: Tokens::RADIUS_MD,
        );
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->content?->layout($constraints) ?? $constraints->constrain(new Size(0.0, 0.0));
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $this->content?->paint($canvas, $x, $y);
    }
}
