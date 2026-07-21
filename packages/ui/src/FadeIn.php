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
 * Fades (and slides slightly upward) a child in on mount, via a CSS
 * keyframe animation (see .phpx-animate in assets/css/input.css) — no JS.
 * A keyframe animation always plays when its element is inserted into the
 * DOM, which is what makes this work through nav.js's innerHTML swaps and
 * StreamBuilder/FutureBuilder's fragment replacements without any
 * mutation-observer or restart logic.
 *
 * This is NOT Flutter's AnimatedContainer — there's no reactivity/diffing
 * layer here to detect a prop change and tween between two states, only an
 * enter animation that plays once per mount. See
 * ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md item #4.
 */
final class FadeIn extends Widget
{
    public function __construct(
        private readonly Widget $child,
        private readonly int $durationMs = 400,
        private readonly int $delayMs = 0,
        private readonly string $curve = Curves::EASE_OUT,
        private readonly int $distancePx = 12,
    ) {
    }

    public static function make(
        Widget $child,
        int $durationMs = 400,
        int $delayMs = 0,
        string $curve = Curves::EASE_OUT,
        int $distancePx = 12,
    ): self {
        return new self($child, $durationMs, $delayMs, $curve, $distancePx);
    }

    public function render(): string
    {
        $style = sprintf(
            '--phpx-duration:%dms;--phpx-delay:%dms;--phpx-curve:%s;--phpx-distance:%dpx;',
            $this->durationMs,
            $this->delayMs,
            htmlspecialchars($this->curve, ENT_QUOTES),
            $this->distancePx,
        );

        return sprintf(
            '<div class="phpx-animate" style="%s">%s</div>',
            $style,
            $this->child->render(),
        );
    }
}
