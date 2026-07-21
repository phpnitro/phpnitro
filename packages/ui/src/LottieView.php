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
 * Lottie equivalent — plays a bundled Lottie JSON animation via the
 * vendored lottie-web (assets/js/vendor/lottie.min.js, MIT, see
 * assets/js/vendor/NOTICE.md), not a CDN — works fully offline.
 * $src is a path to a .json animation file served by this app (e.g.
 * "/assets/animations/success.json").
 */
final class LottieView extends Widget
{
    public function __construct(
        private readonly string $src,
        private readonly bool $loop = true,
        private readonly bool $autoplay = true,
        private readonly string $classes = 'w-32 h-32',
    ) {
    }

    public static function make(string $src, bool $loop = true, bool $autoplay = true, string $classes = 'w-32 h-32'): self
    {
        return new self($src, $loop, $autoplay, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<div data-lottie-view data-src="%s" data-loop="%d" data-autoplay="%d" class="%s"></div>',
            htmlspecialchars($this->src, ENT_QUOTES),
            $this->loop ? 1 : 0,
            $this->autoplay ? 1 : 0,
            htmlspecialchars($this->classes, ENT_QUOTES),
        );
    }
}
