<?php

require __DIR__ . '/../vendor/autoload.php';

use Engine\App\ApiPage;
use Engine\App\DevicePage;
use Engine\App\HomePage;
use Engine\App\LoginPage;
use Engine\App\ProductPage;
use Engine\App\SettingsPage;
use Engine\App\WidgetsDialogsPage;
use Engine\App\WidgetsFirebaseAuthPage;
use Engine\App\WidgetsFormsPage;
use Engine\App\WidgetsIndexPage;
use Engine\App\WidgetsLayoutPage;
use Engine\App\WidgetsMapsPage;
use Engine\App\WidgetsMediaPage;
use Engine\App\WidgetsStepperPage;
use Engine\Csrf;
use Engine\Database\Database;
use Engine\Navigation;
use Engine\PageRenderer;
use Engine\Router;
use Symfony\Component\Dotenv\Dotenv;

// ".env" in dev; "env" (no dot) in the Android bundle, because AAPT drops
// hidden files from APK assets. One level up either way: public -> project
// root (the bundle mirrors this same layout, see bin/phpx's cmdBundleAndroid).
foreach ([__DIR__ . '/../.env', __DIR__ . '/../env'] as $envFile) {
    if (file_exists($envFile)) {
        (new Dotenv())->loadEnv($envFile);
        break;
    }
}

// Single place that pins the SQLite path — a single autoloader/vendor now
// covers the whole app, so this runs once here instead of once per entry
// point (no more separate lib/backend/bootstrap.php).
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

// The backend (Symfony HttpFoundation) is handled from this SAME PHP
// process — no second server/port to launch, which is what makes it
// available implicitly, including inside the Android app.
if (str_starts_with($path, '/api/')) {
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
    '/settings' => SettingsPage::class,
    '/device' => DevicePage::class,
    '/api' => ApiPage::class,
    '/product/{id}' => ProductPage::class,
    '/login' => LoginPage::class,
    '/widgets' => WidgetsIndexPage::class,
    '/widgets/layout' => WidgetsLayoutPage::class,
    '/widgets/forms' => WidgetsFormsPage::class,
    '/widgets/media' => WidgetsMediaPage::class,
    '/widgets/maps' => WidgetsMapsPage::class,
    '/widgets/dialogs' => WidgetsDialogsPage::class,
    '/widgets/stepper' => WidgetsStepperPage::class,
    '/widgets/firebase-auth' => WidgetsFirebaseAuthPage::class,
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

        if (!Navigation::isPartial()) {
            header('Location: ' . $_SERVER['REQUEST_URI'], true, 303);
            exit;
        }
        // Partial mode: same path, just re-render below with the new theme.
    } elseif (isset($_POST['_action'])) {
        $screen = new $resolved['class']($resolved['params']);
        $data = array_diff_key($_POST, ['_action' => null, '_token' => null]);
        $redirect = $screen->handle($_POST['_action'], $data) ?? $path;

        if (!Navigation::isPartial()) {
            header('Location: ' . $redirect, true, 303);
            exit;
        }

        if (PageRenderer::isExternalUrl($redirect)) {
            PageRenderer::redirectExternally($redirect);
        }

        // Partial mode: an action can redirect to a DIFFERENT screen (e.g.
        // checkout -> /order/5) — re-resolve that path and render its page
        // as the fragment, instead of making nav.js do a second round-trip
        // to follow a redirect it would otherwise receive.
        $path = $redirect;

        try {
            $resolved = $router->resolve($path);
        } catch (\RuntimeException $e) {
            http_response_code(404);
            echo '<h1>404 — page introuvable</h1>';
            exit;
        }
    }
}

$screen = new $resolved['class']($resolved['params']);
$widgetTree = $screen->build();

PageRenderer::render($widgetTree, $path, $_ENV['APP_NAME'] ?? 'PHP Engine', [
    '/assets/js/gestures.js',
    '/assets/js/device.js',
    '/assets/js/dialogs.js',
    '/assets/js/stream.js',
    '/assets/js/future.js',
    '/assets/js/nav.js',
], $debug);
