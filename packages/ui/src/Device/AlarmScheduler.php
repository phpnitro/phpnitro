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
 * android_alarm_manager_plus equivalent — schedules a notification to
 * fire after a delay via AlarmManager + AlarmReceiver, even if this
 * app's process has since been killed (see NativeDeviceBridge.kt's
 * scheduleAlarm(), genuinely shared with the WebView path's
 * WebAppInterface.scheduleAlarm() — same AlarmReceiver either way). An
 * action-string builder, not a widget: attach
 * AlarmScheduler::scheduleAction() to any Button.
 *
 * $requestCode is a caller-chosen identifier — scheduling a second alarm
 * with the SAME code replaces the first (AlarmManager's own semantics,
 * PendingIntent.FLAG_UPDATE_CURRENT), and it's also the notification's
 * own id, so two different alarms need two different codes to both show
 * up. setExactAndAllowWhileIdle under the hood, so it fires under Doze
 * too — needs SCHEDULE_EXACT_ALARM + USE_EXACT_ALARM, already declared
 * in android/app/src/main/AndroidManifest.xml (a normal permission, no
 * runtime prompt). Fire-and-forget, no result field — there's nothing
 * to read back until the notification actually fires, later, outside
 * this request/response cycle entirely.
 */
final class AlarmScheduler
{
    public static function scheduleAction(int $requestCode, int $delaySeconds, string $title, string $message): string
    {
        return 'device:alarmschedule:' . "{$requestCode}:{$delaySeconds}:" . rawurlencode($title) . ':' . rawurlencode($message);
    }
}
