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
 * A dark (or light) section inside an otherwise-opposite screen — Tokens
 * was a single static flag until this existed (see its own docblock),
 * meaning ?dark=1 was all-or-nothing for the whole screen. Tokens::push()/
 * pop() now back a real stack instead.
 *
 * The $builder is a CLOSURE, not a plain Widget, and that's not
 * incidental: most widgets resolve their colors from Tokens (ink(),
 * surface()...) the moment they're CONSTRUCTED (`new Checkbox(...)`
 * reads Tokens::ink() right there in its constructor), not later during
 * layout()/paint() — a plain `new Theme($alreadyBuiltChild, dark: true)`
 * would push the override AFTER every widget inside $alreadyBuiltChild
 * had already resolved its colors against the ambient theme, too late to
 * matter. Deferring construction into a closure Theme itself invokes
 * (only after pushing) is what makes the override actually reach them —
 * exactly why LazyList/Grid take an itemBuilder closure instead of a
 * pre-built widget array, same underlying reason.
 *
 * A handful of widgets (BottomSheet, CircularProgress, PasswordField,
 * Scaffold, Skeleton, Slider, Spinner) resolve Tokens:: again inside
 * their OWN layout()/paint() rather than just their constructor — the
 * override is pushed again around both of THIS widget's layout()/paint()
 * calls too, not just construction, so those still see it correctly.
 */
final class Theme implements Widget
{
    private readonly Widget $child;

    /**
     * @param callable(): Widget $builder Invoked immediately, but only after the override is pushed.
     */
    public function __construct(
        private readonly bool $dark,
        \Closure $builder,
    ) {
        Tokens::push($this->dark);
        try {
            $this->child = $builder();
        } finally {
            Tokens::pop();
        }
    }

    public function layout(Constraints $constraints): Size
    {
        Tokens::push($this->dark);
        try {
            return $this->child->layout($constraints);
        } finally {
            Tokens::pop();
        }
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        Tokens::push($this->dark);
        try {
            $this->child->paint($canvas, $x, $y);
        } finally {
            Tokens::pop();
        }
    }
}
