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
 * Schedules/cancels a periodic background ping via WorkManager (see
 * NativeDeviceBridge.kt's scheduleBackgroundTask()/cancelBackgroundTask(),
 * BackgroundPingWorker) — an action-string builder, not a widget: attach
 * BackgroundTask::scheduleAction()/BackgroundTask::cancelAction() to any
 * Button.
 *
 * $endpoint is a path on THIS app's own server (BackgroundPingWorker
 * resolves it against the same host/port the app was launched with) —
 * not an arbitrary external URL. $intervalMinutes is clamped to a
 * 15-minute floor on the Kotlin side — WorkManager's own documented
 * minimum for periodic work, not a PhpNitro-specific limit. Only one
 * background task can be scheduled at a time (a unique work name) —
 * scheduling a second one replaces neither cancels nor stacks, it's a
 * no-op if one with the same name is already pending (KEEP policy). No
 * result field for either.
 */
final class BackgroundTask
{
    public static function scheduleAction(string $endpoint, int $intervalMinutes = 15): string
    {
        return 'device:bgschedule:' . rawurlencode($endpoint) . ":{$intervalMinutes}";
    }

    public static function cancelAction(): string
    {
        return 'device:bgcancel';
    }
}
