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

/** Read-only, next 30 days — needs READ_CALENDAR granted. Named CalendarEvents, not Calendar: PHP's own Calendar-ish built-ins/DateTime live in the global namespace, avoid any confusion. */
final class CalendarEvents
{
    public static function onClick(string $outputId): string
    {
        return sprintf("phpxDevice.listUpcomingEvents('%s')", $outputId);
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
