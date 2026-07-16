<?php

namespace Backend;

use Backend\Controller\ProductController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes an already-built Request to a Controller and returns the Response.
 * Called in-process from the single front controller (public/index.php) —
 * no second server/port, backend/ has no public/ of its own. One
 * project-wide composer.json/vendor covers this and lib/pages both, so
 * there's no separate autoloader to require here.
 */
final class Kernel
{
    public function handle(Request $request): Response
    {
        $products = new ProductController();

        return match ($request->getPathInfo()) {
            '/api/health' => new JsonResponse(['status' => 'ok']),
            '/api/products' => $products->list($request),
            default => new JsonResponse(['error' => 'Not found'], 404),
        };
    }
}
