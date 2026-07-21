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
 * android_alarm_manager_plus equivalent — schedules a notification to fire
 * after a delay, even if the app has since been killed (Android
 * AlarmManager + AlarmReceiver, see WebAppInterface.kt's scheduleAlarm()).
 * A JS trigger, not a widget — attach it to any button via
 * Button::make($label, onClick: AlarmScheduler::onClick(1, 3600, ...)).
 */
final class AlarmScheduler
{
    public static function onClick(int $requestCode, int $delaySeconds, string $title, string $message): string
    {
        return sprintf(
            'phpxDevice.scheduleAlarm(%d, %d, %s, %s)',
            $requestCode,
            $delaySeconds,
            json_encode($title, JSON_THROW_ON_ERROR),
            json_encode($message, JSON_THROW_ON_ERROR),
        );
    }
}
