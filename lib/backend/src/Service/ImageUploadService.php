<?php

namespace Backend\Service;

/**
 * Decodes a data: URL (what ImagePicker puts in its hidden field, or what
 * any Form posts when a picked image travels as base64) and saves it under
 * backend/var/uploads/, returning a name the caller can build a URL from.
 */
final class ImageUploadService
{
    private const ALLOWED_MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        private readonly string $uploadDir = __DIR__ . '/../../var/uploads',
    ) {
    }

    /**
     * @throws \InvalidArgumentException if $dataUrl isn't a supported image data: URL
     */
    public function saveDataUrl(string $dataUrl): string
    {
        if (!preg_match('/^data:(image\/[a-zA-Z]+);base64,(.+)$/', $dataUrl, $matches)) {
            throw new \InvalidArgumentException('Not a valid image data: URL.');
        }

        [, $mimeType, $base64] = $matches;

        $extension = self::ALLOWED_MIME_EXTENSIONS[$mimeType] ?? null;
        if ($extension === null) {
            throw new \InvalidArgumentException("Unsupported image type: {$mimeType}");
        }

        $bytes = base64_decode($base64, true);
        if ($bytes === false) {
            throw new \InvalidArgumentException('Invalid base64 data.');
        }

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        file_put_contents("{$this->uploadDir}/{$filename}", $bytes);

        return $filename;
    }
}
