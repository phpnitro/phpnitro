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
 * A standalone row of dots — the same visual PageView already draws
 * inline for its own prev/next nav, pulled out so a screen driving its
 * own paging state (a client-side ClientTabs panel, a custom carousel)
 * can show the same indicator without going through PageView itself.
 */
final class PageIndicator implements Widget
{
    private readonly Widget $content;

    public function __construct(int $count, int $currentIndex, float $dotSize = 8.0)
    {
        $clamped = max(0, min($count - 1, $currentIndex));

        $dots = array_map(
            static fn (int $i): Widget => new Padding(
                EdgeInsets::only(left: $i > 0 ? Tokens::SPACE_XS : 0),
                new Container(width: $dotSize, height: $dotSize, radius: $dotSize / 2, background: $i === $clamped ? Tokens::ink() : Tokens::border()),
            ),
            range(0, max(0, $count - 1)),
        );

        $this->content = Flex::row($dots);
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
