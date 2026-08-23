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
 * Counts calendar events in the next 30 days via CalendarContract (see
 * NativeDeviceBridge.kt's upcomingEventsCount()) — an action-string
 * builder, not a widget: attach CalendarEvents::countAction() to any
 * Button.
 *
 * READ_CALENDAR is only ever checked, never requested — pair with
 * Permission::requestAction('calendar') first if it might not be granted
 * yet. Result lands in $_GET[$outputField] as "N événements" or
 * "Permission requise".
 */
final class CalendarEvents
{
    public static function countAction(string $outputField = 'calendar_out'): string
    {
        return "device:calendar:{$outputField}";
    }

    public static function result(string $outputField = 'calendar_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
