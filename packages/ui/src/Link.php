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

final class Link extends Widget
{
    public function __construct(
        private readonly string $label,
        private readonly string $href,
        private readonly string $classes = 'text-blue-600 hover:underline',
    ) {
    }

    public static function make(string $label, string $href, string $classes = 'text-blue-600 hover:underline'): self
    {
        return new self($label, $href, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            htmlspecialchars($this->href, ENT_QUOTES),
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->label, ENT_QUOTES),
        );
    }
}
