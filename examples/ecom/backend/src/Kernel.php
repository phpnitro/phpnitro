<?php

namespace Backend;

use Backend\Controller\ProductController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
