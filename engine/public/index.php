<?php

require __DIR__ . '/../vendor/autoload.php';

use Engine\App\HomePage;
use Engine\App\SettingsPage;
use Engine\Router;

session_start();

$router = new Router([
    '/' => HomePage::class,
    '/settings' => SettingsPage::class,
]);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

try {
    $screenClass = $router->resolve($path);
} catch (\RuntimeException $e) {
    http_response_code(404);
    echo '<h1>404 — page introuvable</h1>';
    exit;
}

$screen = new $screenClass();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action'])) {
    $screen->handle($_POST['_action']);
    header('Location: ' . $_SERVER['REQUEST_URI'], true, 303);
    exit;
}

$widgetTree = $screen->build();

?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHP Engine</title>
    <link rel="stylesheet" href="tailwind.css">
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <?= $widgetTree->render() ?>
</body>

</html>