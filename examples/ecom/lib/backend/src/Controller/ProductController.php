<?php

namespace Backend\Controller;

use Backend\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ProductController
{
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse((new ProductRepository())->findAll());
    }
}
