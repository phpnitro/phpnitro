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
 * Called in-process from the single front controller (public/index.php) —
 * no second server/port, backend/ has no public/ of its own. One project-wide
 * composer.json/vendor covers this and lib/pages both, so there's no separate
 * autoloader to require here.
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
