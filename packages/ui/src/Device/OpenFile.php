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
 * Opens a file already on the device with whatever app the OS resolves
 * for its MIME type — via a FileProvider content:// URI, not a raw
 * file:// path (Android has forbidden exposing those across apps since
 * API 24, the FileProvider indirection is not optional). An action-string
 * builder, not a widget: attach OpenFile::openAction() to any Button.
 *
 * $content/$mimeType/$fileName describe a small file this call writes
 * into the app's own storage first (context.filesDir) before handing it
 * off — the same "demo content, not an arbitrary byte pipe" scope
 * FileSaver takes, since a GET-based action string can't carry an
 * arbitrary file's real bytes. A project that already has a real file on
 * disk (e.g. something downloaded via a background task) would use the
 * same FileProvider mechanism directly from Kotlin instead of going
 * through this generic demo path.
 */
final class OpenFile
{
    public static function openAction(string $content, string $fileName = 'document.txt', string $mimeType = 'text/plain'): string
    {
        return 'device:openfile:' . rawurlencode($fileName) . ':' . rawurlencode($mimeType) . ':' . rawurlencode($content);
    }
}
