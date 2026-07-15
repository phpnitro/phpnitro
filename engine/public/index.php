<?php

require __DIR__ . '/../vendor/autoload.php';

use Engine\App\ApiPage;
use Engine\App\DevicePage;
use Engine\App\HomePage;
use Engine\App\ProductPage;
use Engine\App\SettingsPage;
use Engine\Router;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->loadEnv(__DIR__ . '/../.env');

session_start();

$router = new Router([
    '/' => HomePage::class,
    '/settings' => SettingsPage::class,
    '/device' => DevicePage::class,
    '/api' => ApiPage::class,
    '/product/{id}' => ProductPage::class,
]);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

try {
    $resolved = $router->resolve($path);
} catch (\RuntimeException $e) {
    http_response_code(404);
    echo '<h1>404 — page introuvable</h1>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? null) === 'toggleTheme') {
    $_SESSION['theme'] = ($_SESSION['theme'] ?? 'light') === 'dark' ? 'light' : 'dark';
    header('Location: ' . $_SERVER['REQUEST_URI'], true, 303);
    exit;
}

$screen = new $resolved['class']($resolved['params']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action'])) {
    $screen->handle($_POST['_action']);
    header('Location: ' . $_SERVER['REQUEST_URI'], true, 303);
    exit;
}

$widgetTree = $screen->build();
$theme = $_SESSION['theme'] ?? 'light';

?>
<!doctype html>
<html lang="fr" class="<?= $theme === 'dark' ? 'dark' : '' ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'PHP Engine', ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="tailwind.css">
    <script src="gestures.js" defer></script>
    <script src="device.js" defer></script>
</head>

<body class="bg-gray-50 dark:bg-gray-900 dark:text-gray-100 min-h-screen flex items-center justify-center">
    <?= $widgetTree->render() ?>
</body>

</html>