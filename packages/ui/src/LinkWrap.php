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
 * Like Link, but wraps an arbitrary Widget (a whole card, not just text) in
 * an <a href>. Useful for "the entire product card is clickable" layouts.
 */
final class LinkWrap extends Widget
{
    public function __construct(
        private readonly Widget $child,
        private readonly string $href,
        private readonly string $classes = 'block',
    ) {
    }

    public static function make(Widget $child, string $href, string $classes = 'block'): self
    {
        return new self($child, $href, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            htmlspecialchars($this->href, ENT_QUOTES),
            htmlspecialchars($this->classes, ENT_QUOTES),
            $this->child->render(),
        );
    }
}
