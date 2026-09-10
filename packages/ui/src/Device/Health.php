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
 * Reads today's step count, best-effort (see NativeDeviceBridge.kt's
 * healthStepCount(), Health Connect's aggregate()) — an action-string
 * builder, not a widget: attach Health::stepsAction() to any Button.
 *
 * "unsupported" is the honest, common result: Health Connect (Android)
 * is a separate app most devices don't ship with pre-installed, and iOS
 * HealthKit requires its own dedicated entitlement/usage-description
 * setup a generic demo screen doesn't carry. Same "check, never
 * request" contract every other permission-gated read here follows —
 * result lands in $_GET[$outputField] as 'unsupported', 'Permission
 * requise', or 'N pas aujourd'hui'.
 */
final class Health
{
    public static function stepsAction(string $outputField = 'health_out'): string
    {
        return "device:health:{$outputField}";
    }

    public static function result(string $outputField = 'health_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
