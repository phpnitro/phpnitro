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
 * Switches the app's launcher icon at runtime — Android's own
 * activity-alias mechanism (PackageManager::setComponentEnabledSetting()),
 * see NativeDeviceBridge.kt's setAppIcon() for the real implementation.
 * An action-string builder, not a widget: attach
 * DynamicIcon::setAction() to any Button.
 *
 * $iconKey must match an activity-alias declared in the project's own
 * AndroidManifest.xml, named ".DynamicIcon<PascalKey>" — "holiday_2026"
 * resolves to ".DynamicIconHoliday2026". Two variants ("default", "alt")
 * ship in the demo app's own manifest; a project isn't capped at two —
 * add another ".DynamicIcon<Key>" alias (its own icon/roundIcon
 * resources, same shape as the existing two) and it works immediately,
 * no Kotlin change needed (setAppIcon() discovers every matching alias
 * via PackageManager, not a hardcoded list).
 *
 * The one thing that genuinely can't be dynamic: the icon FILES
 * themselves. Every variant an app can ever switch to must be declared
 * — and its APK size paid for — at build time; no framework, this one
 * included, can materialize a brand new icon at runtime. That's a real
 * Android/Play Store constraint, not a limitation of this package.
 */
final class DynamicIcon
{
    public static function setAction(string $iconKey): string
    {
        return "device:appicon:{$iconKey}";
    }
}
