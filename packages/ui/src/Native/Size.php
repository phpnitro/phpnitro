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

final class Size
{
    public function __construct(
        public readonly float $width,
        public readonly float $height,
    ) {
    }

    public static function zero(): self
    {
        return new self(0.0, 0.0);
    }
}
