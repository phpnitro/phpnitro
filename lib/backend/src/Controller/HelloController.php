<?php

namespace Backend\Controller;

use Backend\Repository\VisitRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class HelloController
{
    public function health(Request $request): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    public function hello(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Hello from ' . ($_ENV['APP_NAME'] ?? 'backend'),
        ]);
    }

    public function visits(Request $request): JsonResponse
    {
        $repository = new VisitRepository();
        $repository->recordVisit();

        return new JsonResponse(['visits' => $repository->countVisits()]);
    }
}
