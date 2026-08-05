<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Mime;

/**
 * MIME type lookups — mime_content_type() (the fileinfo extension) needs
 * a real file on disk to sniff; this adds the extension-based fallback
 * PHP has no builtin for (guessing a type before a file exists, e.g. from
 * an upload's original filename), and the reverse lookup for
 * OpenFile/FileSaver's own $mimeType parameters.
 */
final class Mime
{
    private const EXTENSION_MAP = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'text/javascript',
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'zip' => 'application/zip',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /** Sniffs the real file's content if $path exists on disk; falls back to guessFromExtension() otherwise. */
    public static function guessFromPath(string $path): string
    {
        if (is_file($path)) {
            $type = mime_content_type($path);
            if ($type !== false) {
                return $type;
            }
        }

        return self::guessFromExtension(pathinfo($path, PATHINFO_EXTENSION));
    }

    public static function guessFromExtension(string $extension): string
    {
        return self::EXTENSION_MAP[strtolower(ltrim($extension, '.'))] ?? 'application/octet-stream';
    }

    public static function extensionFor(string $mimeType): ?string
    {
        static $flipped = null;
        $flipped ??= array_flip(self::EXTENSION_MAP);

        return $flipped[strtolower($mimeType)] ?? null;
    }
}
