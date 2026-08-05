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
 * Scans a QR code or barcode — a photo is taken via the system camera app
 * (same ActivityResultContracts.TakePicturePreview Camera uses) and
 * decoded on-device with ML Kit's barcode-scanning model, see
 * NativeRenderPocActivity's own scanQrPicture launcher.
 *
 * An action-string builder, not a widget: attach QrScanner::scanAction()
 * to any Button of your choosing.
 *
 * Deliberately NOT a live-scanning preview (point the camera and it
 * detects automatically, no shutter tap) — that needs a persistent
 * camera preview surface, which NativeDeviceBridge.kt's own docblock
 * already names as the one thing genuinely still WebView-only on this
 * native Canvas-only rendering path. Decode-a-still is the honest scope
 * this can actually deliver without that: point, tap the shutter, get
 * the decoded value back through $outputField on the next request.
 *
 * Result is the raw decoded string, or 'Aucun code détecté' if the photo
 * didn't contain a readable code, or 'Annulé' if the camera was
 * dismissed without taking a photo — check for those two exact strings
 * if a screen needs to tell "no code" apart from "cancelled" from a real
 * value.
 */
final class QrScanner
{
    public static function scanAction(string $outputField = 'qr_out'): string
    {
        return "device:scanqr:{$outputField}";
    }

    public static function result(string $outputField = 'qr_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
