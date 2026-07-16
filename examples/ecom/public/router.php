<?php

/**
 * Router script for PHP's built-in dev server: serve existing static files
 * (tailwind.css...) as-is, /assets/* from the sibling assets/ directory
 * (this example has no phpx-style sync step), otherwise hand off to the
 * front controller so it can resolve the request through Engine\Router.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

if (str_starts_with($path, '/assets/')) {
    $file = __DIR__ . '/../' . ltrim($path, '/');

    if (is_file($file)) {
        $mimeTypes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'woff2' => 'font/woff2',
        ];
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
        readfile($file);

        return true;
    }
}

$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
