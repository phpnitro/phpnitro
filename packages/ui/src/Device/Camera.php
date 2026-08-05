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
 * Launches the system camera app (ActivityResultContracts.TakePicturePreview,
 * see NativeRenderPocActivity.kt) — an action-string builder, not a widget:
 * attach Camera::captureAction() to any Button (or other tappable) of your
 * choosing instead of being handed a pre-made one.
 *
 * No CAMERA permission needed on the PHP/manifest side — a system-app
 * intent like this handles its own permission internally, the calling app
 * never touches the camera hardware directly. See Microphone for the
 * capability that DOES need a runtime permission.
 *
 * Result always lands in $_GET['photo_out'] on the next request — a
 * placeholder description today ("Photo capturée (WxH)"), not the actual
 * image bytes; see NativeRenderPocActivity.kt's takePicturePreview
 * callback if a project needs the real bitmap. Fixed key, not
 * configurable — the "camera" action's Kotlin handler doesn't parse a
 * custom output field.
 */
final class Camera
{
    public static function captureAction(): string
    {
        return 'device:camera';
    }

    public static function result(): ?string
    {
        return $_GET['photo_out'] ?? null;
    }
}
