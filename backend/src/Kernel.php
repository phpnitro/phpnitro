<?php

namespace Backend;

use Backend\Controller\FcmController;
use Backend\Controller\HelloController;
use Backend\Controller\UploadController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes an already-built Request to a Controller and returns the Response.
 * Extracted out of public/index.php so the exact same dispatch logic can be
 * called in-process from ui/'s router (single on-device PHP process) as
 * well as from the standalone backend/public/index.php (local API-only dev
 * server via `phpx serve:backend`).
 */
final class Kernel
{
    public function handle(Request $request): Response
    {
        $path = $request->getPathInfo();

        if (preg_match('#^/api/uploads/(.+)$#', $path, $matches)) {
            return (new UploadController())->serve($matches[1]);
        }

        $controller = new HelloController();
        $fcm = new FcmController();
        $upload = new UploadController();

        return match ($path) {
            '/api/health' => $controller->health($request),
            '/api/hello' => $controller->hello($request),
            '/api/visits' => $controller->visits($request),
            '/api/fcm/register' => $fcm->register($request),
            '/api/fcm/count' => $fcm->count($request),
            '/api/upload' => $upload->store($request),
            default => new JsonResponse(['error' => 'Not found'], 404),
        };
    }
}
