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
 * Records a short audio clip via MediaRecorder (NativeDeviceBridge.kt's
 * recordAudioClip()) — an action-string builder, not a widget: attach
 * VoiceRecorder::recordAction() to any Button of your choosing.
 *
 * A real RECORD_AUDIO runtime permission prompt is involved (see
 * NativeRenderPocActivity's ActivityResultContracts.RequestPermission()
 * launcher). A denied prompt reports "permission_denied" through
 * $outputField the same round-trip way a successful recording does —
 * check for that value, not just truthiness.
 */
final class VoiceRecorder
{
    public static function recordAction(string $outputField = 'mic_out', int $durationMs = 2000): string
    {
        return "device:mic:{$outputField}:{$durationMs}";
    }

    public static function result(string $outputField = 'mic_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
