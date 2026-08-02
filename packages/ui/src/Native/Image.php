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
 * A network image, loaded and decoded off the main thread by
 * NativeCanvasView (see ImageLoader.kt) and cached in memory by URL.
 * Needs an explicit width/height — there's no synchronous way to know an
 * image's intrinsic size at PHP layout time without fetching it, so this
 * behaves like Flutter's Image.network() used with an explicit size:
 * the layout engine reserves the box immediately, the bitmap fills it
 * once the async load finishes.
 */
final class RenderImage implements RenderNode
{
    public function __construct(
        private readonly string $url,
        private readonly float $width,
        private readonly float $height,
        private readonly float $radius = 0.0,
    ) {
    }

    public function layout(Constraints $constraints): Size
    {
        return $constraints->constrain(new Size($this->width, $this->height));
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $canvas->image($x, $y, $this->width, $this->height, $this->url, $this->radius);
    }
}
