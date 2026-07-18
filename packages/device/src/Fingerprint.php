<?php

namespace Engine\Device;

use Engine\Html;
use Engine\Widget;

/**
 * Triggers a native BiometricPrompt (fingerprint/face unlock) and writes
 * the result text into the element $outputId names. A JS trigger, not a
 * widget — attach onClick() to any button and place outputElement()
 * wherever the result should appear.
 */
final class Fingerprint
{
    public static function onClick(string $outputId): string
    {
        return sprintf("phpxDevice.fingerprint('%s')", $outputId);
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
