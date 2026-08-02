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
 * The native-tree equivalent of Engine\PageView — pages switch via tap
 * (dot indicators + prev/next chevrons) instead of a horizontal swipe
 * gesture. NativeCanvasView.kt's touch handling is built around one
 * whole-screen vertical scroll region; a true nested horizontal
 * swipe-to-page region is real, separate gesture-routing work this
 * doesn't attempt. $onPageAction receives the target page index appended
 * (e.g. "toggle:layout_page" with meta next carrying the index), same
 * "$_GET drives what's on screen" idiom every other stateful native
 * widget already uses — the caller decides the field name.
 */
final class PageView implements Widget
{
    private readonly Widget $content;

    /**
     * @param array<int, Widget> $pages
     */
    public function __construct(
        array $pages,
        int $currentPage,
        string $fieldName,
        float $height = 96.0,
    ) {
        $count = count($pages);
        $clamped = max(0, min($count - 1, $currentPage));
        $page = $pages[$clamped] ?? new SizedBox(0, 0);

        $dots = array_map(
            static fn (int $i): Widget => new Padding(
                EdgeInsets::only(left: $i > 0 ? Tokens::SPACE_XS : 0),
                new Container(width: 8, height: 8, radius: 4, background: $i === $clamped ? Tokens::ink() : Tokens::border()),
            ),
            range(0, $count - 1),
        );

        $nav = Flex::row([
            new IconCircle('chevron_left', 32.0, action: "toggle:{$fieldName}", meta: ['next' => (string) max(0, $clamped - 1)]),
            new Flexible(new Center(Flex::row($dots))),
            new IconCircle('chevron_right', 32.0, action: "toggle:{$fieldName}", meta: ['next' => (string) min($count - 1, $clamped + 1)]),
        ], crossAxisAlignment: CrossAxisAlignment::CENTER);

        $this->content = Flex::column([
            new Container($page, height: $height),
            new Padding(EdgeInsets::only(top: Tokens::SPACE_SM), $nav),
        ], crossAxisAlignment: CrossAxisAlignment::STRETCH);
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
