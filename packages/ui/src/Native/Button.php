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
 * Tappable+Container+Center+Text/Flex. Pass $width explicitly for a
 * fixed-width CTA; leave it null to hug the label — UNLESS an ancestor
 * Flex::column/row with CrossAxisAlignment::STRETCH hands this a TIGHT
 * width constraint, in which case that wins (a real "fill this stretched
 * slot" intent, not the same thing as "any bounded constraint means
 * fill" — see this class's own layout() docblock for why that
 * distinction has to be made here, at layout time, not in the
 * constructor).
 */
final class Button implements Widget
{
    private readonly Widget $inner;
    private Tappable $content;

    /**
     * @param ?array<string, mixed> $meta Extra data the client needs to handle this action —
     *                                    see Tappable's docblock.
     */
    public function __construct(
        private readonly string $label,
        private readonly string $action,
        private readonly ?string $icon = null,
        private readonly ?float $width = null,
        private readonly float $height = 54.0,
        private readonly ?Color $background = null,
        private readonly ?Color $foreground = null,
        private readonly ?array $meta = null,
    ) {
        $fg = $foreground ?? Color::white();
        $labelNode = new Text($label, Tokens::TEXT_BODY, $fg->toHex(), bold: true);

        $this->inner = $icon === null ? $labelNode : Flex::row([
            new Icon($icon, 18, $fg->toHex()),
            new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), $labelNode),
        ], mainAxisAlignment: MainAxisAlignment::CENTER, crossAxisAlignment: CrossAxisAlignment::CENTER);
    }

    /**
     * Real bug found testing a fresh scaffold, in two opposite
     * directions: (1) every width-less Button used to silently stretch
     * to its parent's full available width the instant that width was
     * merely BOUNDED, not actually tight — Center/Flex both fill any
     * bounded constraint they're handed, not just a tight one, which is
     * wrong for "as much as you're allowed" but right for a genuine
     * "fill this exact slot" request. (2) The first fix for that (always
     * pre-measuring the label and forcing that as Container's width)
     * went too far the other way: it also ignored a REAL stretch intent
     * from a CrossAxisAlignment::STRETCH ancestor, shrinking buttons
     * that were supposed to fill a stretched column back down to their
     * label's own size. The two cases are only distinguishable at
     * layout() time, by checking whether the incoming constraint is
     * actually TIGHT (minWidth === maxWidth, a real stretch/fill
     * request) versus merely bounded-but-loose (Center's own bug, not
     * this caller's intent) — the constructor never sees this, only
     * layout() does.
     */
    public function layout(Constraints $constraints): Size
    {
        $resolvedWidth = $this->width;
        if ($resolvedWidth === null && $constraints->minWidth === $constraints->maxWidth && $constraints->hasBoundedWidth()) {
            $resolvedWidth = $constraints->maxWidth;
        } elseif ($resolvedWidth === null) {
            $resolvedWidth = $this->inner->layout(new Constraints(0.0, Constraints::INFINITY, 0.0, Constraints::INFINITY))->width
                + 2 * Tokens::SPACE_XL;
        }

        $this->content = new Tappable(
            new Container(
                new Center($this->inner),
                width: $resolvedWidth,
                height: $this->height,
                background: $this->background ?? Tokens::ink(),
                radius: Tokens::RADIUS_PILL,
            ),
            $this->action,
            $this->meta,
        );

        return $this->content->layout($constraints);
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $this->content->paint($canvas, $x, $y);
    }
}
