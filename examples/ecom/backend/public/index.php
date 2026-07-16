<?php

require __DIR__ . '/../vendor/autoload.php';

use Backend\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

foreach ([__DIR__ . '/../../.env', __DIR__ . '/../../env'] as $envFile) {
    if (file_exists($envFile)) {
        (new Dotenv())->loadEnv($envFile);
        break;
    }
}

$request = Request::createFromGlobals();
(new Kernel())->handle($request)->send();
