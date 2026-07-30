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
 * One styled run inside a RenderRichText — Flutter's TextSpan, minimally.
 * Any field left null inherits RenderRichText's own base style, the same
 * "spans override, don't replace" idea Flutter's TextSpan.style has. An
 * $action makes just this run tappable (RenderRichText registers a
 * per-word hit region for it) — a "click here" link inline in a sentence,
 * without wrapping the whole paragraph in a single RenderTappable.
 */
final class TextSpan
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $color = null,
        public readonly ?bool $bold = null,
        public readonly ?float $size = null,
        public readonly ?float $letterSpacing = null,
        public readonly ?string $action = null,
    ) {
    }
}
