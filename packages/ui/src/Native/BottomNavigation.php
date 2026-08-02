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
 * The native-tree equivalent of Engine\BottomNavigation — meant to be
 * handed to Scaffold, which pins it to the viewport bottom via
 * Fixed. Each tab fires "tab:screen" rather than plain
 * "navigate:screen" — NativeRenderPocActivity resets the whole screen
 * stack to that single entry instead of pushing, so switching tabs
 * repeatedly doesn't grow an ever-longer back stack the way drilling into
 * a detail screen should.
 */
final class BottomNavigation implements Widget
{
    public const HEIGHT = 64.0;

    private readonly Widget $content;

    /**
     * @param array<int, array{icon: string, label: string, screen: string}> $items
     */
    public function __construct(float $width, array $items, string $currentScreen, ?Color $activeColor = null)
    {
        $active = $activeColor ?? Tokens::ink();

        $tabs = array_map(function (array $item) use ($active, $currentScreen): Widget {
            $isActive = $item['screen'] === $currentScreen;
            $color = $isActive ? $active : Tokens::inkMuted();

            $tab = new Center(Flex::column([
                new Icon($item['icon'], 22.0, $color->toHex()),
                new Padding(EdgeInsets::only(top: 2.0), new Text($item['label'], Tokens::TEXT_CAPTION, $color->toHex(), bold: $isActive)),
            ], mainAxisAlignment: MainAxisAlignment::CENTER, crossAxisAlignment: CrossAxisAlignment::CENTER));

            return new Flexible(new Tappable($tab, "tab:{$item['screen']}"));
        }, $items);

        $this->content = new Container(
            Flex::row($tabs, crossAxisAlignment: CrossAxisAlignment::STRETCH),
            width: $width,
            height: self::HEIGHT,
            background: Tokens::surface(),
            elevation: 4.0,
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
