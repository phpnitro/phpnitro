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
 * A row of star icons (Material `star`/`star_half`/`star_border`) — the
 * one widget this framework was missing for the single most common
 * e-commerce pattern (a product's average rating, a review's own
 * score), found composing it by hand from plain `Icon`s in an example
 * app before deciding it belonged here instead.
 *
 * Two modes, picked by whether `$name` is passed:
 * - Display only (the common case — showing an existing average, e.g.
 *   4.3): omit `$name`. `$value` supports halves (4.5 renders 4 full
 *   stars + 1 half), not just whole numbers, so a real average doesn't
 *   get rounded away.
 * - Real input (picking a NEW rating, e.g. a review form): pass `$name`.
 *   Each star becomes tappable, firing "toggle:{$name}" with
 *   `meta: ['next' => "<index>"]` — the exact same commit mechanism
 *   Checkbox/NumberPicker/Slider already use (see ScreenNavigation's own
 *   docblock on `toggle:`), so no new client-side action type is needed
 *   just for this widget. A star's own index (1-based) is what lands in
 *   fieldValues[$name] on the next fetch — read it back as (int) on the
 *   PHP side.
 */
final class Rating implements Widget
{
    private readonly Widget $content;

    public function __construct(
        float $value,
        float $size = 20.0,
        int $max = 5,
        string $color = '#F59E0B',
        ?string $name = null,
    ) {
        $stars = [];
        for ($i = 1; $i <= $max; $i++) {
            $icon = new Icon(self::iconFor($value, $i), $size, $color);
            $stars[] = $name !== null
                ? new Tappable($icon, "toggle:{$name}", ['next' => (string) $i])
                : $icon;
        }

        $this->content = Flex::row($stars);
    }

    private static function iconFor(float $value, int $index): string
    {
        if ($value >= $index) {
            return 'star';
        }
        if ($value >= $index - 0.5) {
            return 'star_half';
        }

        return 'star_border';
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
