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
 * WorkManager periodic background execution (android_alarm_manager_plus's
 * "run this repeatedly, even in the background" use case, as distinct from
 * AlarmScheduler's "fire once at a specific time"). Pings $endpoint every
 * $intervalMinutes (WorkManager's own 15-minute floor, not a choice made
 * here) even when the app isn't foregrounded — the worker itself is a
 * dumb HTTP POST (BackgroundPingWorker.kt), it does NOT start the embedded
 * PHP server or run any PHP; point $endpoint at your own hosted backend,
 * not this device's own loopback server.
 *
 * NOT geofencing — that needs Play Services' FusedLocationProviderClient
 * geofencing APIs, a separate dependency not pulled in here.
 */
final class BackgroundTask
{
    public static function scheduleOnClick(string $endpoint, int $intervalMinutes = 15): string
    {
        return sprintf(
            'phpxDevice.scheduleBackgroundTask(%s, %d)',
            json_encode($endpoint, JSON_THROW_ON_ERROR),
            $intervalMinutes,
        );
    }

    public static function cancelOnClick(): string
    {
        return 'phpxDevice.cancelBackgroundTask()';
    }
}
