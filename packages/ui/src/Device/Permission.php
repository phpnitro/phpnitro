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
 * Checks (and if needed, prompts for) a dangerous Android runtime
 * permission — an action-string builder, not a widget: attach
 * Permission::requestAction() to any Button of your choosing, the same
 * way Flutter's permission_handler package hands you a plain
 * Permission.camera.request() call rather than a widget.
 *
 * $permission is one of a fixed whitelist NativeRenderPocActivity itself
 * defines (see its own permissionKeys map) — 'camera', 'microphone',
 * 'location', 'coarse_location', 'contacts', 'calendar', 'notifications',
 * 'bluetooth' — not an arbitrary Android permission string. That
 * whitelist only covers permissions android/app/src/main/AndroidManifest.xml
 * already declares; asking for one this app never declared would throw
 * at the OS level with a confusing message far from the actual mistake,
 * so the Kotlin side rejects an unknown key up front instead
 * ("unknown_permission" comes back through $outputField the same way a
 * real grant/deny does).
 *
 * Result lands in $_GET[$outputField] as 'granted', 'denied', or
 * 'unknown_permission' — check for that value, not just truthiness.
 */
final class Permission
{
    public static function requestAction(string $permission, string $outputField = 'permission_out'): string
    {
        return "device:permission:{$permission}:{$outputField}";
    }

    public static function result(string $outputField = 'permission_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
