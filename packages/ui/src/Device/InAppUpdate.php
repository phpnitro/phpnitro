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
 * Checks whether a newer version is available via Play Core's
 * AppUpdateManager (com.google.android.play:app-update-ktx) and, if so,
 * starts the real Play-drawn update flow — not a custom "new version
 * available" dialog. An action-string builder, not a widget: attach
 * InAppUpdate::checkAction() to any Button.
 *
 * Outside of a real Play Store install, this always reports
 * 'update_not_available' (there's no Play-side release to compare
 * against) — that's Play Core's own behavior, not a bug in this wrapper.
 *
 * Result lands in $_GET[$outputField] as 'update_available',
 * 'update_not_available', or 'update_in_progress'.
 */
final class InAppUpdate
{
    public static function checkAction(string $outputField = 'update_out'): string
    {
        return "device:checkupdate:{$outputField}";
    }

    public static function result(string $outputField = 'update_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
