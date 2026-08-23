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
 * Opens the system share sheet (Intent.ACTION_SEND chooser, see
 * NativeDeviceBridge.kt's share()) — an action-string builder, not a
 * widget: attach Share::shareAction() to any Button.
 *
 * Fire-and-forget, no result field — Android's share sheet reports back
 * to neither the calling app nor this bridge which target (if any) the
 * user picked.
 */
final class Share
{
    public static function shareAction(string $text, string $title = ''): string
    {
        return 'device:share:' . rawurlencode($text) . ':' . rawurlencode($title);
    }
}
