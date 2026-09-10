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
 * Checks for a system-level Reminders capability (see
 * NativeDeviceBridge.kt's remindersState()) — an action-string builder,
 * not a widget: attach Reminders::stateAction() to any Button.
 *
 * Always 'unsupported' on Android: there is no OS-level "Reminders"
 * concept there at all, unlike iOS's own EventKit reminders (EKReminder,
 * a genuinely separate store from Calendar events) — a real, permanent
 * platform gap on Android's side, not a temporary "not implemented
 * yet". Result lands in $_GET[$outputField].
 */
final class Reminders
{
    public static function stateAction(string $outputField = 'reminders_out'): string
    {
        return "device:reminders:{$outputField}";
    }

    public static function result(string $outputField = 'reminders_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
