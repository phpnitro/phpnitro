<?php

/**
 * Router script for PHP's built-in dev server: serve existing static files
 * (tailwind.css, images...) as-is, otherwise hand off to the front controller
 * so it can resolve the request through Engine\Router.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
