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
 * Reads the personal-hotspot on/off state, best-effort (see
 * NativeDeviceBridge.kt's hotspotState()) — an action-string builder,
 * not a widget: attach Hotspot::stateAction() to any Button.
 *
 * No public, officially-supported API exists to read this at all on
 * modern Android (only a `@hide` method reachable via reflection, which
 * Android's own hidden-API restrictions can silently block depending on
 * OEM/version) or on iOS (no public API whatsoever) — 'unsupported' is
 * the honest, expected result on a growing share of real devices, not a
 * bug in either wrapper. Result lands in $_GET[$outputField] as
 * 'unsupported', 'off', or 'on'.
 */
final class Hotspot
{
    public static function stateAction(string $outputField = 'hotspot_out'): string
    {
        return "device:hotspot:{$outputField}";
    }

    public static function result(string $outputField = 'hotspot_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
