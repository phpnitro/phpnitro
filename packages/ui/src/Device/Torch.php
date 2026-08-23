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
 * Toggles the camera flash as a torch (see NativeDeviceBridge.kt's
 * toggleTorch()) — an action-string builder, not a widget: attach
 * Torch::toggleAction() to any Button.
 *
 * Result lands in $_GET[$outputField] as 'on' or 'off' — the new state
 * after toggling, not a boolean. No CAMERA permission needed for
 * torch mode specifically (same as Camera's own docblock).
 */
final class Torch
{
    public static function toggleAction(string $outputField = 'torch_out'): string
    {
        return "device:torch:{$outputField}";
    }

    public static function result(string $outputField = 'torch_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
