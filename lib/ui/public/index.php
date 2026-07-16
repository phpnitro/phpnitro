<?php

require __DIR__ . '/../vendor/autoload.php';

use Engine\App\ApiPage;
use Engine\App\DevicePage;
use Engine\App\HomePage;
use Engine\App\LoginPage;
use Engine\App\ProductPage;
use Engine\App\SettingsPage;
use Engine\Csrf;
use Engine\Router;
use Symfony\Component\Dotenv\Dotenv;

// ".env" in dev; "env" (no dot) in the Android bundle, because AAPT drops
// hidden files from APK assets. Three levels up either way: public -> ui ->
// lib -> project root (the bundle mirrors the same lib/ui, lib/backend
// layout, see bin/phpx's cmdBundleAndroid).
foreach ([__DIR__ . '/../../../.env', __DIR__ . '/../../../env'] as $envFile) {
    if (file_exists($envFile)) {
        (new Dotenv())->loadEnv($envFile);
        break;
    }
}

$debug = ($_ENV['APP_DEBUG'] ?? 'true') === 'true';

if ($debug) {
    set_exception_handler(static function (\Throwable $e): void {
        http_response_code(500);
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES);
        $class = htmlspecialchars($e::class, ENT_QUOTES);
        $file = htmlspecialchars($e->getFile() . ':' . $e->getLine(), ENT_QUOTES);
        $trace = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES);
        echo "<!doctype html><html lang=\"fr\"><head><meta charset=\"utf-8\"><title>Erreur</title></head>"
            . '<body style="font-family:monospace;background:#1a1a2e;color:#eee;padding:2rem">'
            . "<h1 style=\"color:#ff6b6b\">{$class}</h1>"
            . "<p style=\"font-size:1.1rem\">{$message}</p>"
            . "<p style=\"color:#aaa\">{$file}</p>"
            . "<pre style=\"background:#16213e;padding:1rem;border-radius:8px;overflow-x:auto\">{$trace}</pre>"
            . '</body></html>';
    });
} else {
    set_exception_handler(static function (\Throwable $e): void {
        http_response_code(500);
        error_log((string) $e);
        echo '<h1>Erreur interne</h1>';
    });
}

session_start();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// The backend (Symfony HttpFoundation, its own composer project) is served
// from this SAME PHP process — no second server/port to launch, which is
// what makes it available implicitly, including inside the Android app.
if (str_starts_with($path, '/api/')) {
    require dirname(__DIR__, 2) . '/backend/vendor/autoload.php';
    (new \Backend\Kernel())->handle(\Symfony\Component\HttpFoundation\Request::createFromGlobals())->send();
    exit;
}

// Fragment routes: raw widget HTML (no page wrapper), polled by
// StreamBuilder's client-side script (stream.js) to fake "live" content
// without a WebSocket server.
if ($path === '/fragment/server-time') {
    echo \Engine\Text::make('Heure serveur : ' . date('H:i:s'))->render();
    exit;
}

if ($debug && $path === '/_dev/version') {
    $latest = 0;
    foreach (['app', 'php', 'public'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            dirname(__DIR__) . '/' . $dir,
            FilesystemIterator::SKIP_DOTS,
        ));
        foreach ($files as $file) {
            $latest = max($latest, $file->getMTime());
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['version' => $latest]);
    exit;
}

$router = new Router([
    '/' => HomePage::class,
    '/settings' => SettingsPage::class,
    '/device' => DevicePage::class,
    '/api' => ApiPage::class,
    '/product/{id}' => ProductPage::class,
    '/login' => LoginPage::class,
]);

try {
    $resolved = $router->resolve($path);
} catch (\RuntimeException $e) {
    http_response_code(404);
    echo '<h1>404 — page introuvable</h1>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_token'] ?? null)) {
        http_response_code(419);
        echo '<h1>419 — session expirée, recharge la page</h1>';
        exit;
    }

    if (($_POST['_action'] ?? null) === 'toggleTheme') {
        $_SESSION['theme'] = ($_SESSION['theme'] ?? 'light') === 'dark' ? 'light' : 'dark';
        header('Location: ' . $_SERVER['REQUEST_URI'], true, 303);
        exit;
    }

    if (isset($_POST['_action'])) {
        $screen = new $resolved['class']($resolved['params']);
        $data = array_diff_key($_POST, ['_action' => null, '_token' => null]);
        $redirect = $screen->handle($_POST['_action'], $data);
        header('Location: ' . ($redirect ?? $_SERVER['REQUEST_URI']), true, 303);
        exit;
    }
}

$screen = new $resolved['class']($resolved['params']);
$widgetTree = $screen->build();
$theme = $_SESSION['theme'] ?? 'light';

?>
<!doctype html>
<html lang="fr" class="<?= $theme === 'dark' ? 'dark' : '' ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
    <title><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'PHP Engine', ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="/tailwind.css">
    <script src="/gestures.js" defer></script>
    <script src="/device.js" defer></script>
    <script src="/stream.js" defer></script>
    <?php if ($debug) { ?><script src="/dev-reload.js" defer></script><?php } ?>
</head>

<body class="bg-gray-50 dark:bg-gray-900 dark:text-gray-100 min-h-screen">
    <?= $widgetTree->render() ?>
</body>

</html>
