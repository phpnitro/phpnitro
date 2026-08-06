<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Device;

/**
 * Requests the in-app star-rating prompt via Play Core's ReviewManager
 * (com.google.android.play:review-ktx) — the real OS-drawn review sheet,
 * not a custom dialog pretending to be one. An action-string builder, not
 * a widget: attach InAppReview::requestAction() to any Button.
 *
 * Play's own API gives no guarantee the prompt actually shows (quota
 * limits, or no Play Store account on the test device) — that's true of
 * every platform's in-app-review API, not a bug in this wrapper. Outside
 * of a real Play Store install (e.g. a debug build), the flow silently
 * no-ops instead of showing anything, which is expected.
 */
final class InAppReview
{
    public static function requestAction(): string
    {
        return 'device:inappreview';
    }
}
