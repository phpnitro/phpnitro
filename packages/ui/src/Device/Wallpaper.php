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
 * Sets the device's home-screen wallpaper from an image URL (see
 * NativeDeviceBridge.kt's setWallpaper(), WallpaperManager.setBitmap())
 * — an action-string builder, not a widget: attach
 * Wallpaper::setAction() to any Button.
 *
 * $imageUrl must be reachable from the device (same "absolute http(s)://
 * URL" constraint as Engine\Device\Sound). SET_WALLPAPER is a normal
 * Android permission (always granted, no runtime prompt). iOS exposes
 * NO public API to set the wallpaper programmatically at all — the only
 * way there is handing the user a Share Sheet and letting them pick
 * "Use as Wallpaper" manually, a real platform gap, not a narrower
 * implementation of the same capability.
 *
 * Result lands in $_GET[$outputField] as 'Fond d'écran modifié' or an
 * error message.
 */
final class Wallpaper
{
    public static function setAction(string $imageUrl, string $outputField = 'wallpaper_out'): string
    {
        return 'device:wallpaper:' . rawurlencode($imageUrl) . ":{$outputField}";
    }

    public static function result(string $outputField = 'wallpaper_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
