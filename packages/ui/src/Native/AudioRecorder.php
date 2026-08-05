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
 * Records a short audio clip via MediaRecorder (NativeDeviceBridge.kt's
 * recordAudioClip(), see its own docblock) — like CameraButton, the
 * capability already existed but had no PHP-facing widget, just an
 * undocumented "mic" action string a screen would have to hand-wire a
 * Tappable against.
 *
 * Unlike CameraButton (a system-app intent that handles its own
 * permission), this records directly via the mic — a real RECORD_AUDIO
 * runtime permission prompt is involved. That permission was already
 * declared in android/app/src/main/AndroidManifest.xml, but nothing ever
 * actually REQUESTED it at runtime before NativeRenderPocActivity gained
 * a real ActivityResultContracts.RequestPermission() launcher for this —
 * recordAudioClip() always hit its own "permission_denied" branch before
 * that fix, on every real device, every time. A denied prompt reports
 * "permission_denied" through $outputField the same round-trip way a
 * successful recording does — check for that value, not just truthiness.
 *
 * $outputField follows the "mic:field" action convention (a custom key
 * instead of the default "mic_out") — unlike CameraButton's fixed
 * "photo_out", this one IS configurable, because the Kotlin handler
 * already parsed it before this widget existed (built for consistency
 * with getLocation()/showBiometricPrompt()'s own custom-key convention).
 */
final class AudioRecorder implements Widget
{
    private readonly Button $content;

    public function __construct(
        string $label = '🎙️ Enregistrer',
        string $outputField = 'mic_out',
        int $durationMs = 2000,
        ?Color $background = null,
    ) {
        $this->content = new Button($label, "device:mic:{$outputField}:{$durationMs}", background: $background);
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
