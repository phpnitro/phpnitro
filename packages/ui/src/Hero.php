<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine;

/**
 * Flutter's Hero flies a shared element from its position/size on one
 * screen to its position/size on the next. `$tag` identifies the SAME
 * conceptual element across two independent server renders (e.g. a
 * product thumbnail on a list page and the same product's photo on its
 * detail page) — `assets/js/hero.js` uses the FLIP technique (see
 * AnimatedContainer's docblock for the general approach) on
 * `getBoundingClientRect()` instead of computed style: it records the
 * element's on-screen rect before the swap, then applies a CSS `transform`
 * (translate + scale) that makes the newly-inserted element with the same
 * tag instantly LOOK like it's still at the old rect, forces a reflow, and
 * releases to `transform: none` with a transition — the browser animates
 * the element visually flying from its old position/size to its new one.
 *
 * Real limitations, unlike Flutter's Hero: no cross-fade of mismatched
 * content (if the old and new elements render very differently, this
 * flies the box but the content inside just snaps), and no interruption
 * handling for a third navigation mid-flight (the browser will simply cut
 * the transition short, which reads fine in practice but isn't animated
 * away). Two elements sharing a tag on the SAME screen at once will only
 * animate the first one found in `document.querySelectorAll`.
 */
final class Hero extends Widget
{
    public function __construct(
        private readonly Widget $child,
        private readonly string $tag,
        private readonly int $durationMs = 300,
        private readonly string $curve = Curves::EASE_IN_OUT,
    ) {
    }

    public static function make(
        Widget $child,
        string $tag,
        int $durationMs = 300,
        string $curve = Curves::EASE_IN_OUT,
    ): self {
        return new self($child, $tag, $durationMs, $curve);
    }

    public function render(): string
    {
        return sprintf(
            '<div data-hero="%s" data-duration="%d" data-curve="%s">%s</div>',
            htmlspecialchars($this->tag, ENT_QUOTES),
            $this->durationMs,
            htmlspecialchars($this->curve, ENT_QUOTES),
            $this->child->render(),
        );
    }
}
