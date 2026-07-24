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
 * Flashlight toggle, independent of Camera's photo/video capture (which
 * needs a live camera session; this only needs CameraManager.setTorchMode).
 * No web fallback — browsers have no torch API outside a getUserMedia
 * video track's (spottily supported) ImageCapture extension.
 */
final class Torch
{
    public static function onClick(): string
    {
        return 'phpxDevice.toggleTorch()';
    }
}
