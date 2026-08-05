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
 * A bounded integer stepper — decrement/increment buttons around the
 * current value, reusing the exact "toggle:$name" + meta.next mechanism
 * PageView's own prev/next chevrons already use (NativeRenderPocActivity
 * just sets $_GET[$name] to whatever "next" says and refetches). A real
 * scroll-wheel picker (flick to spin through values) would need a new
 * continuous-gesture primitive like Slider's — this is the honest
 * "+/- stepper" scope achievable with what already exists.
 */
final class NumberPicker implements Widget
{
    private readonly Widget $content;

    public function __construct(
        string $name,
        int $value,
        int $min = 0,
        int $max = 100,
        int $step = 1,
    ) {
        $clamped = max($min, min($max, $value));

        $this->content = Flex::row([
            new IconCircle('remove', 36.0, action: "toggle:{$name}", meta: ['next' => (string) max($min, $clamped - $step)]),
            new Flexible(new Center(new Text((string) $clamped, Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true))),
            new IconCircle('add', 36.0, action: "toggle:{$name}", meta: ['next' => (string) min($max, $clamped + $step)]),
        ], crossAxisAlignment: CrossAxisAlignment::CENTER);
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
