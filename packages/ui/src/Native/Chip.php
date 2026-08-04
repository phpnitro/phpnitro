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
 * A small pill-shaped label — a filter tag, a category marker, a
 * removable selection. Pure composition (Container + Text, optionally an
 * Icon and a Tappable "x"), no engine changes needed: the same reason
 * ProgressBar/RadioGroup never needed one either. Pass $onTap for a
 * selectable/toggleable chip (the whole chip becomes tappable, wired to
 * an ordinary action string, no special dispatch); pass $onDismiss for a
 * removable one (a small "x" glyph gets its own tap target instead of
 * eating the whole chip's).
 */
final class Chip implements Widget
{
    private readonly Widget $content;

    public function __construct(
        string $label,
        bool $selected = false,
        ?string $onTap = null,
        ?string $onDismiss = null,
        ?Color $accentColor = null,
    ) {
        $accent = $accentColor ?? Tokens::ink();
        $background = $selected ? $accent : Tokens::surfaceMuted();
        $foreground = $selected ? Color::white() : Tokens::ink();

        $children = [
            new Text($label, Tokens::TEXT_BODY_SMALL, $foreground->toHex(), bold: $selected),
        ];
        if ($onDismiss !== null) {
            $children[] = new Padding(
                EdgeInsets::only(left: Tokens::SPACE_XS),
                new Tappable(new Icon('close', 14.0, $foreground->toHex()), $onDismiss),
            );
        }

        $row = new Container(
            new Padding(
                EdgeInsets::only(left: Tokens::SPACE_MD, right: Tokens::SPACE_MD, top: Tokens::SPACE_XS, bottom: Tokens::SPACE_XS),
                Flex::row($children, crossAxisAlignment: CrossAxisAlignment::CENTER),
            ),
            background: $background,
            radius: Tokens::RADIUS_PILL,
            borderColor: $selected ? null : Tokens::border(),
            borderWidth: $selected ? 0.0 : 1.0,
        );

        $this->content = $onTap !== null ? new Tappable($row, $onTap) : $row;
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
