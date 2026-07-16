<?php

require __DIR__ . '/../vendor/autoload.php';

use Engine\App\AccountPage;
use Engine\App\CartPage;
use Engine\App\CheckoutPage;
use Engine\App\HomePage;
use Engine\App\LoginPage;
use Engine\App\OrderConfirmationPage;
use Engine\App\ProductPage;
use Engine\App\RegisterPage;
use Engine\Csrf;
use Engine\Database\Database;
use Engine\Router;
use Symfony\Component\Dotenv\Dotenv;

// One level up: public -> project root (mirrors the bundle's layout, see
// bundle-android.sh).
foreach ([__DIR__ . '/../.env', __DIR__ . '/../env'] as $envFile) {
    if (file_exists($envFile)) {
        (new Dotenv())->loadEnv($envFile);
        break;
    }
}

// Single place that pins the SQLite path — a single autoloader/vendor now
// covers the whole app, so this runs once here instead of once per entry
// point.
Database::useSqlitePath(__DIR__ . '/../lib/backend/var/data.sqlite');

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

if (str_starts_with($path, '/api/')) {
    (new \Backend\Kernel())->handle(\Symfony\Component\HttpFoundation\Request::createFromGlobals())->send();
    exit;
}

if (preg_match('#^/fragment/order-status/(\d+)$#', $path, $matches)) {
    echo \Engine\Text::make('Statut : ' . ((new \Backend\Repository\OrderRepository())->status((int) $matches[1]) ?? 'inconnu'))->render();
    exit;
}

if ($debug && $path === '/_dev/version') {
    $latest = 0;
    foreach (['lib/pages/app', 'lib/backend/src', 'public'] as $dir) {
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
    '/product/{id}' => ProductPage::class,
    '/cart' => CartPage::class,
    '/checkout' => CheckoutPage::class,
    '/order/{id}' => OrderConfirmationPage::class,
    '/login' => LoginPage::class,
    '/register' => RegisterPage::class,
    '/account' => AccountPage::class,
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
    <title><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Ma Boutique', ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="/tailwind.css">
    <script src="/assets/js/gestures.js" defer></script>
    <script src="/assets/js/device.js" defer></script>
    <script src="/assets/js/stream.js" defer></script>
    <?php if ($debug) { ?><script src="/assets/js/dev-reload.js" defer></script><?php } ?>
</head>

<body class="bg-gray-50 dark:bg-gray-900 dark:text-gray-100 min-h-screen">
    <?= $widgetTree->render() ?>
</body>

</html>
