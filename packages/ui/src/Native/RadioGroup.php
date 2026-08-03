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
 * A column of mutually-exclusive options — reuses Checkbox/Toggle's exact
 * "toggle:" dispatch (NativeRenderPocActivity's generic
 * `action.startsWith("toggle:")` handler already does `fieldValues[name] =
 * meta.next; refetch()`) rather than inventing a new action/handler pair:
 * a radio pick is really just "set this field to a specific string" the
 * same shape as a checkbox flip, just with the value coming from $meta
 * instead of always being the boolean opposite.
 */
final class RadioGroup implements Widget
{
    private readonly Widget $content;

    /** @param array<string, string> $options value => label, in display order */
    public function __construct(string $name, array $options, string $selected, ?Color $accentColor = null, float $size = 22.0)
    {
        $accent = $accentColor ?? Tokens::ink();

        $rows = [];
        foreach ($options as $value => $label) {
            $isSelected = (string) $value === $selected;

            $ring = new Container(width: $size, height: $size, radius: $size / 2, borderColor: $isSelected ? $accent : Tokens::border(), borderWidth: 1.5);
            $circle = $isSelected
                ? new Stack([$ring, new Positioned(new Container(width: $size * 0.5, height: $size * 0.5, radius: $size * 0.25, background: $accent), top: $size * 0.25, left: $size * 0.25)])
                : $ring;

            $row = Flex::row([
                $circle,
                new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), new Text($label, Tokens::TEXT_BODY, Tokens::ink()->toHex())),
            ], crossAxisAlignment: CrossAxisAlignment::CENTER);

            $rows[] = new Padding(EdgeInsets::only(bottom: Tokens::SPACE_SM), new Tappable($row, "toggle:{$name}", ['next' => (string) $value]));
        }

        $this->content = Flex::column($rows);
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
