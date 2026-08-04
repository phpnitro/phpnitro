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
 * A one-shot celebratory particle burst — drop this ANYWHERE in a
 * screen's widget tree (typically behind a condition, e.g. "$orderJust
 * completed") and it fires automatically the moment that render happens,
 * no tap required. Renders as literally nothing itself (Size::zero()) —
 * its only job is calling Canvas::triggerConfetti() from paint(), which
 * just sets a flag toJson() includes as `"confetti": true`.
 * NativeCanvasView.kt's setCommands() checks for that flag on every
 * render and, when present, plays the actual burst — see
 * NativeRenderPocActivity.kt's showConfettiOverlay()/ConfettiView.kt for
 * the client-owned particle simulation, same "continuous animation this
 * request/response pipeline can't express as one static frame, so the
 * client owns the clock" idiom as Spinner.
 *
 * A manual "🎉 again" replay button doesn't need this widget at all —
 * use triggerAction() as any Tappable/Button's action string instead
 * (dispatches through NativeRenderPocActivity's existing "device:"
 * handling, reusing the exact same overlay).
 */
final class Confetti implements Widget
{
    public function layout(Constraints $constraints): Size
    {
        return Size::zero();
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $canvas->triggerConfetti();
    }

    /** For a manual "replay" button — e.g. new Button('🎉 Encore', Confetti::triggerAction()). */
    public static function triggerAction(): string
    {
        return 'device:confetti';
    }
}
