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
 * Triggers a real system notification via native NotificationCompat (see
 * WebAppInterface.showNotification) — works fully offline, no Firebase or
 * network call needed. A JS trigger, not a widget — attach it to any
 * button via Button::make($label, onClick: Notify::onClick(...)).
 */
final class Notify
{
    public static function onClick(string $title, string $message): string
    {
        return sprintf('phpxDevice.notify(%s, %s)', json_encode($title, JSON_THROW_ON_ERROR), json_encode($message, JSON_THROW_ON_ERROR));
    }
}
