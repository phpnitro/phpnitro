<?php

namespace Backend;

use Backend\Controller\FcmController;
use Backend\Controller\HelloController;
use Backend\Controller\UploadController;
use Engine\Database\Database;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes an already-built Request to a Controller and returns the Response.
 * Called in-process from lib/pages' front controller (single on-device PHP
 * process, no second server/port) — this is the only entry point backend/
 * has, it has no public/ of its own.
 */
final class Kernel
{
    public function __construct()
    {
        // packages/database doesn't know where "here" is, so the app pins
        // it once at boot instead of the package guessing a path.
        Database::useSqlitePath(dirname(__DIR__) . '/var/data.sqlite');
    }

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
