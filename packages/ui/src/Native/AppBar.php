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
 * The native-tree equivalent of Engine\AppBar — meant to be handed to
 * Scaffold, not painted directly: Scaffold is what pins it to
 * the viewport top via Fixed while the body scrolls underneath (an
 * AppBar painted on its own, mid-tree, would just scroll away like
 * everything else).
 */
final class AppBar implements Widget
{
    public const HEIGHT = 56.0;

    private readonly Widget $content;

    public function __construct(
        float $width,
        string $title,
        ?string $backAction = null,
        ?Widget $leading = null,
        ?Color $background = null,
    ) {
        $leadingNode = $leading ?? ($backAction !== null ? new IconCircle('arrow_back', 36.0, action: $backAction) : null);

        $row = $leadingNode !== null
            ? Flex::row([
                $leadingNode,
                new Padding(EdgeInsets::only(left: Tokens::SPACE_MD), new Text($title, Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true)),
            ], crossAxisAlignment: CrossAxisAlignment::CENTER)
            : new Text($title, Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true);

        $this->content = new Container(
            new Padding(EdgeInsets::symmetric(horizontal: Tokens::SPACE_LG), new Align($row, Alignment::CENTER_LEFT)),
            width: $width,
            height: self::HEIGHT,
            background: $background ?? Tokens::surface(),
            elevation: 2.0,
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
