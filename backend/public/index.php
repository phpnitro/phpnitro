<?php

require __DIR__ . '/../vendor/autoload.php';

use Backend\Controller\HelloController;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

(new Dotenv())->loadEnv(__DIR__ . '/../.env');

$request = Request::createFromGlobals();
$controller = new HelloController();

$response = match ($request->getPathInfo()) {
    '/api/health' => $controller->health($request),
    '/api/hello' => $controller->hello($request),
    default => new JsonResponse(['error' => 'Not found'], 404),
};

$response->send();
