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
 * A transient bottom-anchored message — drop this ANYWHERE in a screen's
 * widget tree (typically behind a condition, e.g. "$justSaved") and it
 * fires automatically the moment that render happens, no tap required —
 * same "renders as nothing itself, its only job is flagging the Canvas"
 * shape as Confetti. See Canvas::showSnackbar() and
 * NativeRenderPocActivity.kt's showSnackbarOverlay() for the actual
 * fade-in/wait/fade-out animation, owned entirely client-side.
 */
final class Snackbar implements Widget
{
    public function __construct(
        private readonly string $message,
        private readonly int $durationMs = 3000,
    ) {
    }

    public function layout(Constraints $constraints): Size
    {
        return Size::zero();
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $canvas->showSnackbar($this->message, $this->durationMs);
    }
}
