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
 * Flutter's AnimatedContainer tweens between two states of a rebuilt
 * widget — impossible to replicate literally here (nav.js replaces
 * #phpx-content's innerHTML wholesale on every action/navigation, there is
 * no reactivity/diffing layer to detect "this is the same widget, some of
 * its properties changed", see ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md
 * item #4 and FadeIn's docblock). `assets/js/animated-container.js`
 * approximates it with the FLIP technique instead: `$key` is this
 * container's stable identity across two independent server renders. On
 * `phpx:beforeSwap` (fired with the OLD DOM still in place — see nav.js)
 * it snapshots the computed style of every element sharing that key; once
 * the new HTML lands, it freezes the new element at the OLD computed
 * values, forces a reflow, then releases to the new element's real
 * (already-rendered) values with a CSS transition — the browser
 * interpolates background-color/width/height/border-radius/padding/opacity
 * automatically, without this class needing to know what actually changed.
 *
 * If two renders never share the same $key, nothing animates — it just
 * renders like a plain Container. $key only needs to be unique among
 * AnimatedContainers visible on screen at once, not globally.
 */
final class AnimatedContainer extends Widget
{
    public function __construct(
        private readonly Widget $child,
        private readonly string $key,
        private readonly string $classes = 'p-4',
        private readonly ?Color $background = null,
        private readonly ?Rounded $rounded = null,
        private readonly int $durationMs = 300,
        private readonly string $curve = Curves::EASE_IN_OUT,
    ) {
    }

    public static function make(
        Widget $child,
        string $key,
        string $classes = 'p-4',
        ?Color $background = null,
        ?Rounded $rounded = null,
        int $durationMs = 300,
        string $curve = Curves::EASE_IN_OUT,
    ): self {
        return new self($child, $key, $classes, $background, $rounded, $durationMs, $curve);
    }

    private function resolvedClasses(): string
    {
        return implode(' ', array_filter([
            $this->classes,
            $this->background?->backgroundClass(),
            $this->rounded?->value,
        ]));
    }

    public function render(): string
    {
        return sprintf(
            '<div data-animated-container="%s" data-duration="%d" data-curve="%s" class="%s">%s</div>',
            htmlspecialchars($this->key, ENT_QUOTES),
            $this->durationMs,
            htmlspecialchars($this->curve, ENT_QUOTES),
            htmlspecialchars($this->resolvedClasses(), ENT_QUOTES),
            $this->child->render(),
        );
    }
}
