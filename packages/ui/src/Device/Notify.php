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
 * Posts a real system notification (see NativeDeviceBridge.kt's
 * showNotification(), channel "phpx_default") — an action-string
 * builder, not a widget: attach Notify::showAction() to any Button.
 *
 * POST_NOTIFICATIONS (API 33+) is only ever checked, never requested —
 * pair with Permission::requestAction('notifications') first if it
 * might not be granted yet; a denied/missing permission makes this a
 * silent no-op rather than a crash. Fire-and-forget, no result field.
 */
final class Notify
{
    public static function showAction(string $title, string $message): string
    {
        return 'device:notify:' . rawurlencode($title) . ':' . rawurlencode($message);
    }
}
