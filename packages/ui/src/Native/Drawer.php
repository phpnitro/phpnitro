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
 * The native-tree equivalent of Engine\Drawer — a full-screen scrim plus
 * a left-anchored panel, meant to be handed to Scaffold's $drawer
 * param (which paints it via Fixed, screen-relative, on top of
 * everything else including the AppBar/BottomNavigation). There's no
 * client-side open/close animation state on this pipeline (see
 * Fixed's docblock — every paint is one-shot): the caller decides
 * whether the drawer exists in the tree at all based on a server-known
 * "is it open" flag (see NativeHomeScreen's $_GET['drawer_open']),
 * same "$_GET drives what's on screen" idiom every other stateful native
 * widget already uses.
 */
final class Drawer implements Widget
{
    private readonly Widget $content;

    /**
     * @param array<int, array{label: string, icon: string, action: string}> $items
     */
    public function __construct(
        float $screenWidth,
        float $viewportHeight,
        array $items,
        string $title = 'Menu',
    ) {
        $panelWidth = min(288.0, $screenWidth * 0.85);

        // A semi-transparent scrim needs an ARGB hex color
        // (Engine\Color has no alpha channel, being a Tailwind-class
        // abstraction) — CustomPaint takes a raw color string
        // straight through to Canvas::rect(), which Android's
        // Color.parseColor() accepts as #AARRGGBB.
        $scrim = new Tappable(
            CustomPaint::make($screenWidth, $viewportHeight)->rect(0, 0, $screenWidth, $viewportHeight, '#66000000'),
            'toggle:drawer_open',
            ['next' => ''],
        );

        $rows = array_map(
            static fn (array $item): Widget => new ListTile($item['label'], null, $item['icon'], action: $item['action']),
            $items,
        );

        $panel = new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_LG),
                Flex::column([
                    new Text($title, Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_LG), Flex::column(array_map(
                        static fn (Widget $row): Widget => new Padding(EdgeInsets::only(bottom: Tokens::SPACE_SM), $row),
                        $rows,
                    ), crossAxisAlignment: CrossAxisAlignment::STRETCH)),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $panelWidth,
            height: $viewportHeight,
            background: Tokens::surface(),
            elevation: 8.0,
        );

        $this->content = new Stack([$scrim, $panel]);
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
