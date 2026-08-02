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
 * A splash screen the developer composes from real widgets — a logo
 * wrapped in Animated for a scale/fade-in, a Lottie loop,
 * whatever the brand needs — instead of a fixed built-in layout. Wrapping
 * that content in Splash is what turns it into an actual splash:
 * paint() queues Canvas::autoNavigate($nextScreen, $durationMs), so
 * NativeRenderPocActivity.kt pushes $nextScreen on its own once the timer
 * elapses, no tap required.
 *
 * This is deliberately separate from the OS-level splash
 * (Theme.App.Starting / androidx.core:core-splashscreen, shown while the
 * embedded PHP server boots) — that one is intentionally minimal per
 * Android's own SplashScreen API guidance (a static/simple icon, gone the
 * instant the first frame is ready) and can't host a Lottie loop or a
 * multi-second brand animation. Splash is the first *native-rendered*
 * screen instead: a real screen the app navigates through, so it can be as
 * elaborate as the content composed inside it.
 */
final class Splash implements Widget
{
    private Size $size;

    public function __construct(
        private readonly Widget $content,
        private readonly string $nextScreen,
        private readonly int $durationMs = 1800,
    ) {
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $this->size = $this->content->layout($constraints);

        return $this->size;
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $canvas->autoNavigate($this->nextScreen, $this->durationMs);
        $this->content->paint($canvas, $x, $y);
    }
}
