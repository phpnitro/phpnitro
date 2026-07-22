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
 * Records $durationMs of real audio via native MediaRecorder (see
 * WebAppInterface.kt's recordAudioClip — confirmed on real hardware that
 * the WebView-mediated getUserMedia({audio:true}) unreliably fails with
 * "Could not start audio source" on some devices, even with the
 * permission already granted) and plays it back, writing status text into
 * the element $outputId names. Falls back to plain getUserMedia only if
 * no native bridge is present (browser testing). The developer places
 * outputElement() wherever they want and attaches onClick() to their own
 * button, instead of a widget rendering both together.
 */
final class Microphone
{
    public static function onClick(string $outputId, int $durationMs = 3000): string
    {
        return sprintf("phpxDevice.recordAudioClip('%s', %d)", $outputId, $durationMs);
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
