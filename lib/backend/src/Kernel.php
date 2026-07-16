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
 * Called in-process from lib/pages' front controller (single on-device PHP
 * process, no second server/port) — this is the only entry point backend/
 * has, it has no public/ of its own.
 *
 * Callers reach this class through bootstrap.php (never vendor/autoload.php
 * directly), which is where the SQLite path gets pinned — a page that
 * instantiates a Repository directly, without ever touching Kernel, still
 * needs that pinning, so it can't live in Kernel's constructor.
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
