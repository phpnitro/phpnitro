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
 * Opens a system settings screen — this app's own detail page (to let the
 * user flip a permission this app was denied), or a device-wide screen
 * like Wi-Fi/location/notifications. An action-string builder, not a
 * widget: attach AppSettings::openAction() to any Button.
 *
 * $screen is one of a fixed whitelist the Kotlin side maps to the real
 * android.provider.Settings action constant — 'app' (this app's detail
 * page, the most common case: "permission denied, go enable it"), 'wifi',
 * 'location', 'notifications', 'bluetooth'. An unknown key falls back to
 * 'app'.
 */
final class AppSettings
{
    public static function openAction(string $screen = 'app'): string
    {
        return "device:appsettings:{$screen}";
    }
}
