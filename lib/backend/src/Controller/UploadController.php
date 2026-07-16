<?php

namespace Backend\Controller;

use Backend\Service\ImageUploadService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UploadController
{
    public function store(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $dataUrl = is_array($data) ? ($data['image'] ?? null) : null;

        if (!is_string($dataUrl) || $dataUrl === '') {
            return new JsonResponse(['error' => 'Missing "image" data: URL in request body.'], 400);
        }

        try {
            $filename = (new ImageUploadService())->saveDataUrl($dataUrl);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse(['filename' => $filename, 'url' => "/api/uploads/{$filename}"]);
    }

    private const MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    public function serve(string $filename): Response
    {
        $safeName = basename($filename);
        $path = __DIR__ . '/../../var/uploads/' . $safeName;

        if (!is_file($path)) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        // Set the mime type explicitly (by extension) rather than relying on
        // ext-fileinfo for auto-detection — the cross-compiled Android PHP
        // build isn't guaranteed to have it enabled.
        $extension = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', self::MIME_TYPES[$extension] ?? 'application/octet-stream');

        return $response;
    }
}
