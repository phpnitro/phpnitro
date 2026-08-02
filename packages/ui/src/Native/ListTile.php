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
 * A leading-icon-circle + title/subtitle + trailing row, as a NativeCard.
 * Covers both shapes that showed up duplicated across screens: a plain
 * trailing value (NativeSettingsScreen's "Couleur d'accent — blue") via
 * $trailingText, or a second icon-circle (NativeDocumentsScreen's
 * checkmark/add-button) via $trailingIcon. Pass $action to make the
 * whole row tappable.
 */
final class NativeListTile implements RenderNode
{
    private readonly RenderNode $content;

    public function __construct(
        string $title,
        ?string $subtitle,
        string $leadingIcon,
        ?Color $leadingBackground = null,
        ?Color $leadingColor = null,
        ?string $trailingIcon = null,
        ?Color $trailingBackground = null,
        ?Color $trailingColor = null,
        ?string $trailingText = null,
        ?Color $borderColor = null,
        float $borderWidth = 1.0,
        ?string $action = null,
        // Escape hatch for a subtitle line that isn't plain muted text —
        // NativeDocumentsScreen's red tracked-uppercase "OBLIGATOIRE"
        // badge, for instance. Wins over $subtitle when given.
        ?RenderNode $subtitleNode = null,
        // Extra data $action's handler needs without a second round-trip —
        // a "select:" action's options, same as NativeSelectBox's own use
        // of RenderTappable's $meta. Ignored when $action is null.
        ?array $meta = null,
    ) {
        $trailing = match (true) {
            $trailingIcon !== null => new NativeIconCircle(
                $trailingIcon,
                30,
                background: $trailingBackground,
                iconColor: $trailingColor,
            ),
            $trailingText !== null => new RenderText($trailingText, Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),
            default => null,
        };

        $subtitleLine = $subtitleNode ?? ($subtitle !== null ? new RenderText($subtitle, Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()) : null);

        $titleColumn = new Flexible(new RenderPadding(
            EdgeInsets::only(left: Tokens::SPACE_MD),
            $subtitleLine !== null
                ? RenderFlex::column([
                    new RenderText($title, Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true),
                    new RenderPadding(EdgeInsets::only(top: 3), $subtitleLine),
                ])
                : new RenderText($title, Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true),
        ));

        $row = RenderFlex::row(array_filter([
            new NativeIconCircle($leadingIcon, 36, background: $leadingBackground, iconColor: $leadingColor),
            $titleColumn,
            $trailing,
        ]), crossAxisAlignment: CrossAxisAlignment::CENTER);

        $card = new NativeCard(
            $row,
            padding: EdgeInsets::symmetric(Tokens::SPACE_LG, Tokens::SPACE_MD),
            borderColor: $borderColor,
            borderWidth: $borderWidth,
        );

        $this->content = $action !== null ? new RenderTappable($card, $action, $meta) : $card;
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->content->layout($constraints);
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $this->content->paint($canvas, $x, $y);
    }
}
