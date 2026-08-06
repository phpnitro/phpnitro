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
 * A pill-radius tappable button — NativeDocumentsScreen's "Continuer" and
 * NativeOtpScreen's "Vérifier" were both this shape hand-built from
 * Tappable+Container+Center+Text/Flex. Pass
 * $width explicitly for a full-width CTA (there's no "stretch to parent"
 * shortcut without a real width in this constraint system — same reason
 * Flutter's own ElevatedButton needs a SizedBox/Expanded wrapper to go
 * full-width).
 */
final class Button implements Widget
{
    private readonly Tappable $content;

    /**
     * @param ?array<string, mixed> $meta Extra data the client needs to handle this action —
     *                                    see Tappable's docblock.
     */
    public function __construct(
        string $label,
        string $action,
        ?string $icon = null,
        ?float $width = null,
        float $height = 54.0,
        ?Color $background = null,
        ?Color $foreground = null,
        ?array $meta = null,
    ) {
        $fg = $foreground ?? Color::white();
        $labelNode = new Text($label, Tokens::TEXT_BODY, $fg->toHex(), bold: true);

        $inner = $icon === null ? $labelNode : Flex::row([
            new Icon($icon, 18, $fg->toHex()),
            new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), $labelNode),
        ], mainAxisAlignment: MainAxisAlignment::CENTER, crossAxisAlignment: CrossAxisAlignment::CENTER);

        $this->content = new Tappable(
            new Container(
                new Center($inner),
                width: $width,
                height: $height,
                background: $background ?? Tokens::ink(),
                radius: Tokens::RADIUS_PILL,
            ),
            $action,
            $meta,
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
