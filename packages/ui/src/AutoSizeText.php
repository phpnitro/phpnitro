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
 * auto_size_text equivalent — shrinks font-size until the text fits its
 * container (assets/js/autosize-text.js), down to $minSize.
 */
final class AutoSizeText extends Widget
{
    public function __construct(
        private readonly string $content,
        private readonly int $minSize = 10,
        private readonly int $maxSize = 32,
        private readonly string $classes = 'whitespace-nowrap overflow-hidden',
    ) {
    }

    public static function make(
        string $content,
        int $minSize = 10,
        int $maxSize = 32,
        string $classes = 'whitespace-nowrap overflow-hidden',
    ): self {
        return new self($content, $minSize, $maxSize, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<span data-autosize-text data-min-size="%d" data-max-size="%d" class="%s">%s</span>',
            $this->minSize,
            $this->maxSize,
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->content, ENT_QUOTES),
        );
    }
}
