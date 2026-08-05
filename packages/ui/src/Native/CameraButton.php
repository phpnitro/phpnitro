<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Native;

use Engine\Color;

/**
 * Launches the system camera app (ActivityResultContracts.TakePicturePreview,
 * see NativeRenderPocActivity.kt) — the capability itself already existed
 * (NativeDeviceBridge.kt's own docblock: "Camera/image-picker capture ARE
 * covered"), what was missing was any PHP-facing way to trigger it without
 * hand-writing a Tappable against the undocumented "camera" action string.
 *
 * No CAMERA permission needed on the PHP/manifest side for this one — a
 * system-app intent like this handles its own permission internally, the
 * calling app never touches the camera hardware directly. See
 * AudioRecorder for the capability that DOES need a runtime permission
 * (RECORD_AUDIO records via MediaRecorder directly, no system app).
 *
 * Result always lands in $_GET['photo_out'] on the next request — a
 * placeholder description today ("Photo capturée (WxH)"), not the actual
 * image bytes; see NativeRenderPocActivity.kt's takePicturePreview
 * callback if a project needs the real bitmap. Fixed key, not
 * configurable — unlike AudioRecorder's "mic:field" convention, the
 * "camera" action's Kotlin handler doesn't parse a custom output field.
 */
final class CameraButton implements Widget
{
    private readonly Button $content;

    public function __construct(
        string $label = '📷 Prendre une photo',
        ?Color $background = null,
    ) {
        $this->content = new Button($label, 'device:camera', background: $background);
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->content->layout($constraints);
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $this->content->paint($canvas, $x, $y);
    }
}
