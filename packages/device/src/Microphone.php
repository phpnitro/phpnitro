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
 * Activates the microphone (getUserMedia audio) and writes its result
 * text into the element $outputId names — the developer places
 * outputElement() wherever they want and attaches onClick() to their own
 * button, instead of a widget rendering both together.
 */
final class Microphone
{
    public static function onClick(string $outputId): string
    {
        return sprintf("phpxDevice.openMicrophone('%s')", $outputId);
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
