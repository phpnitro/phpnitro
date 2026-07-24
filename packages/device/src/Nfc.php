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

use Engine\Html;
use Engine\Widget;

/**
 * Read-only NDEF tag scanning. NFC is push-based, not poll-based like
 * Bluetooth: there's nothing to "call" to get a tag, so this mirrors
 * Sensors' start/stop-listening shape instead — the native side dispatches
 * a foreground scan while listening is on, and reports whatever tag shows
 * up whenever it shows up. No write support (writing NDEF records to a tag
 * is a separate, riskier capability, left out here).
 */
final class Nfc
{
    public static function startOnClick(string $outputId): string
    {
        return sprintf("phpxDevice.startNfc('%s')", $outputId);
    }

    public static function stopOnClick(): string
    {
        return 'phpxDevice.stopNfc()';
    }

    public static function outputElement(string $id, string $classes = 'text-sm text-gray-500 dark:text-gray-400'): Widget
    {
        return Html::raw(sprintf(
            '<span id="%s" class="%s"></span>',
            htmlspecialchars($id, ENT_QUOTES),
            htmlspecialchars($classes, ENT_QUOTES),
        ));
    }
}
