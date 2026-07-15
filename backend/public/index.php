<?php

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

(new Dotenv())->loadEnv(__DIR__ . '/../.env');

$request = Request::createFromGlobals();

$response = match ($request->getPathInfo()) {
    '/api/health' => new JsonResponse(['status' => 'ok']),
    '/api/hello' => new JsonResponse([
        'message' => 'Hello from ' . ($_ENV['APP_NAME'] ?? 'backend'),
    ]),
    default => new JsonResponse(['error' => 'Not found'], 404),
};

$response->send();
