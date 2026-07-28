<?php

require __DIR__ . '/../vendor/autoload.php';

use Engine\App\ApiPage;
use Engine\App\AppNav;
use Engine\App\DevicePage;
use Engine\App\HomePage;
use Engine\App\LoginPage;
use Engine\App\ProductPage;
use Engine\App\SettingsPage;
use Engine\App\WidgetsCountriesPage;
use Engine\App\WidgetsDialogsPage;
use Engine\App\WidgetsFirebaseAuthPage;
use Engine\App\WidgetsFormsPage;
use Engine\App\WidgetsIndexPage;
use Engine\App\WidgetsLayoutPage;
use Engine\App\WidgetsMapsPage;
use Engine\App\WidgetsMediaPage;
use Engine\App\WidgetsStepperPage;
use Engine\BottomNavigation;
use Engine\Center;
use Engine\Column;
use Engine\Container;
use Engine\Csrf;
use Engine\Database\Database;
use Engine\Color;
use Engine\Html;
use Engine\Icon;
use Engine\Link;
use Engine\Native\Constraints;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Flexible;
use Engine\Native\NativeCanvas;
use Engine\Native\RenderCenter;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderIcon;
use Engine\Native\RenderPadding;
use Engine\Native\RenderTappable;
use Engine\Native\RenderText;
use Engine\Native\Tokens;
use Engine\Navigation;
use Engine\PageRenderer;
use Engine\Router;
use Engine\Text;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Shared by both 404 sites below (initial route resolution, and a partial
 * action's redirect target resolving to nothing) — replaces a bare,
 * unstyled `<h1>` with a properly centered page matching the rest of the
 * app's look. Also fixes a real bug: the partial-mode call site used to
 * echo raw HTML while nav.js's request() always does `await
 * response.json()` on that path — a 404 after a redirect would throw a
 * JSON parse error client-side instead of showing anything. Routing
 * through PageRenderer::render() means it now respects
 * Navigation::isPartial() like every other response.
 */
function renderNotFound(bool $debug): never
{
    $body = Container::make(
        Center::make(Container::make(
            Column::make([
                Html::raw(Icon::warning('w-16 h-16 text-gray-400 dark:text-gray-500 mx-auto')),
                Text::make('404', 'text-5xl font-bold text-gray-900 dark:text-gray-100 text-center'),
                Text::make('Cette page n\'existe pas.', 'text-gray-500 dark:text-gray-400 text-center'),
                Link::make("Retour à l'accueil", '/'),
            ], 'flex flex-col items-center gap-3'),
            'p-8',
        )),
        'min-h-screen',
    );

    http_response_code(404);
    PageRenderer::render($body, '/404', $_ENV['APP_NAME'] ?? 'PHP Engine', [], $debug, showBottomNav: false);
}

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

// Phase 0 of docs/proposals/moteur-rendu-natif.md — a parallel, experimental
// rendering path that bypasses the HTML pipeline entirely: raw JSON draw
// commands, fetched and replayed by NativeCanvasView.kt against a real
// Android Canvas. Not part of the normal Router below on purpose.
if ($path === '/native/demo') {
    header('Content-Type: application/json');
    echo \Engine\NativeDrawCommand::make()
        ->rect(40, 200, 300, 120, '#2563EB', 24)
        ->text(60, 260, 'Rendu natif (Canvas)', '#FFFFFF', 22)
        ->text(60, 300, 'Phase 0 — pas de WebView ici', '#DBEAFE', 14)
        ->toJson();
    exit;
}

// Phase 2: a real widget tree (Column/Row/Container/Text, flex, padding,
// text wrapping) run through the packages/ui/src/Native layout engine —
// proves the constraint-based layout algorithm itself, not just that a
// hardcoded rect/text pair can reach a Canvas. See
// docs/proposals/moteur-rendu-natif.md for the phased plan this belongs to.
if ($path === '/native/layout-demo') {
    header('Content-Type: application/json');

    $screenWidth = (float) ($_GET['width'] ?? 360);

    // php -S spawns a fresh process per request — no in-memory global
    // survives between the initial render and a later tap — so the tap
    // count has to live on disk to actually persist across the round-trip
    // NativeCanvasView.kt -> NativeRenderPocActivity -> here. sys_temp_dir
    // (not lib/backend/var, which cmdBundleAndroid() never copies into the
    // APK's assets) is guaranteed to exist and be writable both in local
    // dev and on-device — PhpServer.kt already points PHP's sys_temp_dir
    // ini setting at the app's real cache directory.
    $tapCountFile = sys_get_temp_dir() . '/phpnitro_native_demo_taps.txt';
    if (($_GET['action'] ?? null) === 'increment') {
        $current = is_file($tapCountFile) ? (int) file_get_contents($tapCountFile) : 0;
        file_put_contents($tapCountFile, (string) ($current + 1));
    }
    $tapCount = is_file($tapCountFile) ? (int) file_get_contents($tapCountFile) : 0;

    // Modeled directly on captures/Documents.png — a minimalist checklist
    // screen: near-black ink on white, thin gray borders instead of
    // shadows, a pill-radius black CTA at the bottom. $tapCount stands in
    // for "how many required documents are done" so the tappable button
    // still demonstrates a genuine server round-trip.
    $requiredDone = min($tapCount, 2);

    $iconCircle = static fn (string $icon, Color $background, Color $iconColor, float $diameter = 40.0): RenderContainer => new RenderContainer(
        new RenderCenter(new RenderIcon($icon, $diameter * 0.5, $iconColor->toHex())),
        width: $diameter,
        height: $diameter,
        radius: $diameter / 2,
        background: $background,
    );

    $documentRow = static function (string $title, string $subtitle, bool $required, bool $done) use ($iconCircle): RenderContainer {
        return new RenderContainer(
            new RenderPadding(
                EdgeInsets::symmetric(Tokens::SPACE_LG, Tokens::SPACE_MD),
                RenderFlex::row([
                    $done
                        ? $iconCircle('check_circle', Tokens::successMuted(), Tokens::success(), 36)
                        : $iconCircle('document', Tokens::surfaceMuted(), Tokens::inkSecondary(), 36),
                    new Flexible(new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_MD), RenderFlex::column([
                        new RenderText($title, Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true),
                        new RenderPadding(
                            EdgeInsets::only(top: 3),
                            $required
                                ? new RenderText('OBLIGATOIRE', Tokens::TEXT_CAPTION, Tokens::danger()->toHex(), bold: true, letterSpacing: 0.04)
                                : new RenderText($subtitle, Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                        ),
                    ]))),
                    $done
                        ? $iconCircle('check', Tokens::successMuted(), Tokens::success(), 30)
                        : $iconCircle('plus', Tokens::ink(), Color::white(), 30),
                ], crossAxisAlignment: CrossAxisAlignment::CENTER),
            ),
            background: Tokens::surface(),
            radius: Tokens::RADIUS_LG,
            borderColor: $done ? Color::green(400) : Tokens::border(),
            borderWidth: $done ? 1.5 : 1.0,
        );
    };

    $tree = new RenderContainer(
        RenderFlex::column([
            // Top bar: back circle + thin progress line, then a step caption.
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    RenderFlex::row([
                        $iconCircle('arrow_back', Tokens::surfaceMuted(), Tokens::ink()),
                        new Flexible(new RenderPadding(
                            EdgeInsets::only(left: Tokens::SPACE_MD, top: 18),
                            new RenderContainer(height: 3, radius: 2, background: Tokens::ink()),
                        )),
                    ], crossAxisAlignment: CrossAxisAlignment::CENTER),
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_SM), new RenderText('Étape 3/4', Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex())),
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_LG), new RenderText('Documents requis', Tokens::TEXT_DISPLAY, Tokens::ink()->toHex(), bold: true)),
                    new RenderPadding(
                        EdgeInsets::only(top: 6),
                        new RenderText('Formats acceptés : PDF, JPG, PNG — 10 Mo max par fichier.', Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),
                    ),
                ]),
            ),
            // Content area: light gray background, same as the capture.
            new RenderContainer(
                new RenderPadding(
                    EdgeInsets::symmetric(Tokens::SPACE_XL, Tokens::SPACE_LG),
                    RenderFlex::column([
                        $documentRow('Permis de conduire', '', true, $requiredDone >= 1),
                        new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_MD), $documentRow('Permis moto', 'si compétence moto', false, false)),
                        new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_MD), $documentRow('Assurance professionnelle', '', true, false)),
                        new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_MD), $documentRow('Pièce d\'identité', '', true, $requiredDone >= 2)),
                        new RenderPadding(
                            EdgeInsets::only(top: Tokens::SPACE_LG),
                            new RenderContainer(
                                new RenderPadding(
                                    EdgeInsets::all(Tokens::SPACE_MD),
                                    RenderFlex::row([
                                        new RenderIcon('warning', 18, Tokens::danger()->toHex()),
                                        new Flexible(new RenderPadding(
                                            EdgeInsets::only(left: Tokens::SPACE_SM),
                                            new RenderText('Veuillez ajouter les documents obligatoires pour continuer.', Tokens::TEXT_BODY_SMALL, Tokens::danger()->toHex()),
                                        )),
                                    ]),
                                ),
                                background: Color::red(50),
                                radius: Tokens::RADIUS_MD,
                            ),
                        ),
                        // Real tappable region — server-driven state, same
                        // phase-3 round-trip as before, now standing in
                        // for "mark the next required document done".
                        new RenderPadding(
                            EdgeInsets::only(top: Tokens::SPACE_XL),
                            new RenderTappable(
                                new RenderContainer(
                                    new RenderCenter(new RenderText(
                                        $requiredDone >= 2 ? 'Continuer' : "Valider un document ({$requiredDone}/2)",
                                        Tokens::TEXT_BODY,
                                        '#FFFFFF',
                                        bold: true,
                                    )),
                                    height: 54,
                                    background: Tokens::ink(),
                                    radius: Tokens::RADIUS_PILL,
                                ),
                                action: 'increment',
                            ),
                        ),
                    ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
                ),
                background: Tokens::surfaceMuted(),
            ),
        ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
        background: Tokens::surface(),
    );

    $tree->layout(new Constraints($screenWidth, $screenWidth, 0, Constraints::INFINITY));

    $canvas = new NativeCanvas();
    $tree->paint($canvas, 0, 0);
    echo $canvas->toJson();
    exit;
}

if ($debug && $path === '/_dev/version') {
    // packages/*/src previously wasn't watched at all — editing a widget
    // (Container.php, FadeIn.php...) took effect on the next PHP request
    // like everything else here, but the WebView never auto-reloaded to
    // show it. Only source directories PHP reads directly on every
    // request belong here — NOT assets/, which only gets copied into
    // public/assets/ once at `phpx serve` startup (syncAssets()), so a
    // reload triggered by an assets/ edit would just show the stale copy.
    $latest = 0;
    $watchedDirs = array_merge(
        ['lib/pages/app', 'lib/backend/src', 'public'],
        array_map(
            static fn (string $dir) => 'packages/' . basename(dirname($dir)) . '/src',
            glob(dirname(__DIR__) . '/packages/*/src', GLOB_ONLYDIR) ?: [],
        ),
    );
    foreach ($watchedDirs as $dir) {
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
    '/widgets/countries' => WidgetsCountriesPage::class,
]);

try {
    $resolved = $router->resolve($path);
} catch (\RuntimeException $e) {
    renderNotFound($debug);
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
            renderNotFound($debug);
        }
    }
}

$screen = new $resolved['class']($resolved['params']);
$widgetTree = $screen->build();

PageRenderer::render($widgetTree, $path, $_ENV['APP_NAME'] ?? 'PHP Engine', [
    '/assets/js/gestures.js',
    '/assets/js/device.js',
    '/assets/js/connectivity.js',
    '/assets/js/autosize-text.js',
    '/assets/js/animated-text.js',
    '/assets/js/infinite-scroll.js',
    '/assets/js/vendor/lottie.min.js',
    '/assets/js/lottie-view.js',
    '/assets/js/canvas.js',
    '/assets/js/animated-container.js',
    '/assets/js/hero.js',
    '/assets/js/dialogs.js',
    '/assets/js/stream.js',
    '/assets/js/future.js',
    '/assets/js/nav.js',
], $debug, BottomNavigation::make(AppNav::items(), variant: BottomNavigation::VARIANT_PILLS), $screen->showsBottomNav(), $screen);
