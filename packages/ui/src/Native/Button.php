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

        // Real bug found testing a fresh scaffold: EVERY width-less
        // Button silently stretched to its parent's full available
        // width instead of hugging its label — contradicting this
        // class's own docblock ("no stretch-to-parent shortcut without
        // an explicit $width"). Root cause traced to Center/Flex both
        // filling any BOUNDED constraint they're handed, not just a
        // TIGHT one (Flutter-faithful for a bounded-and-intended-to-fill
        // slot, wrong for "as much as you're allowed, not as much as you
        // need") — a core layout-engine behavior too widely relied upon
        // elsewhere to change safely without full visual regression
        // testing across every screen. Fixed here instead, self-
        // contained to Button: when no $width is given, lay the content
        // out once against fully unbounded constraints to measure its
        // true hug size, then use that measurement as Container's real
        // width — this scratch layout() call's internal state is
        // harmlessly overwritten by the real layout() pass the normal
        // render pipeline performs afterward (paint() only ever runs
        // after that real pass, never after this measurement one).
        $resolvedWidth = $width;
        if ($resolvedWidth === null) {
            $resolvedWidth = $inner->layout(new Constraints(0.0, Constraints::INFINITY, 0.0, Constraints::INFINITY))->width
                + 2 * Tokens::SPACE_XL;
        }

        $this->content = new Tappable(
            new Container(
                new Center($inner),
                width: $resolvedWidth,
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
