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
 * A real interactive crop UI (drag corners, pinch zoom, aspect-ratio
 * lock) via the CanHub image-cropper library — this Canvas-based
 * pipeline has no 2D drag-a-rectangle-with-resize-handles primitive to
 * hand-roll one from (Slider's own drag gesture is 1D), so this reuses a
 * maintained library the same way QrCode reuses chillerlan/php-qrcode
 * for encoding instead of hand-rolling it. An action-string builder, not
 * a widget: attach ImageCropper::cropAction() to any Button.
 *
 * One action covers the whole "pick a source image, then crop it" flow —
 * the library itself prompts for a source (gallery/camera chooser)
 * before showing the crop UI, a screen doesn't fire a separate pick step
 * first.
 *
 * Result lands in $_GET[$outputField] as a status string ('Image
 * recadrée', 'Erreur', or 'Annulé') — not the cropped image's bytes,
 * same "describe what happened, not the raw payload" scope Camera's
 * photo_out and FileSelector's file_out already take (a GET-based
 * round-trip can't carry an arbitrary image's bytes).
 */
final class ImageCropper
{
    public static function cropAction(string $outputField = 'crop_out'): string
    {
        return "device:cropimage:{$outputField}";
    }

    public static function result(string $outputField = 'crop_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
