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
 * The native equivalent of Engine\LottieView — a real
 * com.airbnb.android.lottie.LottieAnimationView overlaid at this widget's
 * rect, not a hand-rolled frame-by-frame Canvas replay (Lottie's whole
 * point — a continuous animation loop — has no equivalent in a pipeline
 * where PHP computes one still frame per request). NativeCanvasView.kt
 * reconciles the overlay on every render (see Canvas::lottieRegion()),
 * so it autoplays and keeps looping across taps/scrolls the same way it
 * would in any other native app, not just once per screen load.
 *
 * $url can be a remote https:// URL (LottieAnimationView.setAnimationFromUrl())
 * or an asset path under assets/lottie/ bundled with the app
 * (setAnimation("name.json")) — NativeRenderPocActivity tells the two apart
 * by whether $url starts with "http".
 */
final class Lottie implements Widget
{
    private Size $size;

    public function __construct(
        private readonly string $url,
        private readonly float $width,
        private readonly float $height,
        private readonly bool $loop = true,
        private readonly bool $autoplay = true,
        private readonly ?string $key = null,
    ) {
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $this->size = $constraints->constrain(new Size($this->width, $this->height));

        return $this->size;
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $canvas->lottieRegion($this->key ?? $this->url, $x, $y, $this->size->width, $this->size->height, $this->url, $this->loop, $this->autoplay);
    }
}
