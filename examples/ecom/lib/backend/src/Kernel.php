<?php

namespace Backend;

use Backend\Controller\ProductController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Callers reach this class through bootstrap.php (never vendor/autoload.php
 * directly), which is where the SQLite path gets pinned — pages that
 * instantiate a Repository directly, without ever touching Kernel, still
 * need that pinning, so it can't live in Kernel's constructor.
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
