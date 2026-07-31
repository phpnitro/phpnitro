<?php

require __DIR__ . '/../vendor/autoload.php';

use Engine\Database\Database;
use Engine\Native\Constraints;
use Engine\Native\NativeCanvas;
use Symfony\Component\Dotenv\Dotenv;

/**
 * The app has no WebView content pages left (see git history for the last
 * one, WidgetsLayoutPage.php/SettingsPage.php/WidgetsIndexPage.php — all
 * removed once their native conversions reached full parity), so this is
 * plain HTML, not a styled Tailwind page — nothing legitimate should ever
 * hit this route; NativeRenderPocActivity never links here, it only talks
 * to /native/layout-demo.
 */
function renderNotFound(): never
{
    http_response_code(404);
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>404</title></head>'
        . '<body><h1>404</h1><p>Cette page n\'existe pas.</p></body></html>';
    exit;
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
if ($path === '/api' || str_starts_with($path, '/api/')) {
    (new \Backend\Kernel())->handle(\Symfony\Component\HttpFoundation\Request::createFromGlobals())->send();
    exit;
}

// Phase 0 of docs/proposals/moteur-rendu-natif.md — a parallel, experimental
// rendering path that bypasses the HTML pipeline entirely: raw JSON draw
// commands, fetched and replayed by NativeCanvasView.kt against a real
// Android Canvas.
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
    // Point 3 of the "grow the framework" pass: a real performance number
    // instead of an intuition. renderTimeMs covers layout()+paint() only
    // (the PHP-side compute this architecture is actually gambling on
    // staying cheap) — NativeRenderPocActivity separately times the full
    // round-trip (HTTP + this + JSON parse + draw), so a slow frame can be
    // attributed to "PHP is slow" vs "the network hop is slow" vs "Kotlin
    // parsing/drawing is slow" instead of one opaque total.
    $renderStart = microtime(true);

    $screenWidth = (float) ($_GET['width'] ?? 360);
    $screenHeight = (float) ($_GET['height'] ?? 720);
    // Flutter's MediaQuery.of(context).size, for any widget that isn't a
    // screen's own top-level build() and so never received $screenWidth/
    // $screenHeight as an explicit parameter — see MediaQuery::init()'s
    // docblock for why this is safe as a static here.
    \Engine\Native\MediaQuery::init($screenWidth, $screenHeight);
    $screen = $_GET['screen'] ?? 'home';
    $action = $_GET['action'] ?? null;
    // RenderLazyList's windowed prefetch — see NativeCanvasView.kt's
    // checkScrollFollow(). Every screen receives this; only ones that
    // build a RenderLazyList actually read it.
    $scrollY = (float) ($_GET['scroll_y'] ?? 0);

    // php -S spawns a fresh process per request — no in-memory global
    // survives between the initial render and a later tap — so the tap
    // count has to live on disk to actually persist across the round-trip
    // NativeCanvasView.kt -> NativeRenderPocActivity -> here. sys_temp_dir
    // (not lib/backend/var, which cmdBundleAndroid() never copies into the
    // APK's assets) is guaranteed to exist and be writable both in local
    // dev and on-device — PhpServer.kt already points PHP's sys_temp_dir
    // ini setting at the app's real cache directory.
    $tapCountFile = sys_get_temp_dir() . '/phpnitro_native_demo_taps.txt';
    if ($action === 'increment') {
        $current = is_file($tapCountFile) ? (int) file_get_contents($tapCountFile) : 0;
        file_put_contents($tapCountFile, (string) ($current + 1));
    }
    $tapCount = is_file($tapCountFile) ? (int) file_get_contents($tapCountFile) : 0;

    // NativeHomeScreen's counter — deliberately a distinct action name and
    // a distinct Preferences key from Documents' file-backed tap count
    // above (same round-trip idea, different meaning, no reason to share
    // state just because both are "a number that goes up").
    if ($action === 'home_increment') {
        \Engine\Preferences\Preferences::set('native_home_counter', (string) ((int) \Engine\Preferences\Preferences::get('native_home_counter', '0') + 1));
    }
    // Mirrors HomePage.php's onDecrement() — swiping left on
    // NativeGestureDetector's counter area.
    if ($action === 'home_decrement') {
        \Engine\Preferences\Preferences::set('native_home_counter', (string) ((int) \Engine\Preferences\Preferences::get('native_home_counter', '0') - 1));
    }

    // Mirrors SettingsPage::onSetAccent() — the accent SelectBox's pick
    // travels back as a plain field (see NativeSettingsScreen's
    // "select:accent_choice" action), and since Preferences is the same
    // store SettingsPage.php reads/writes, persisting it here is enough to
    // keep both rendering paths in sync.
    if ($screen === 'settings' && isset($_GET['accent_choice'])) {
        \Engine\Preferences\Preferences::set('accent_color', $_GET['accent_choice']);
    }
    // Mirrors SettingsPage::onToggleNativePreview().
    if ($screen === 'settings' && $action === 'togglenativepreview') {
        $currentlyEnabled = \Engine\Preferences\Preferences::get('native_render_preview_enabled', '0') === '1';
        \Engine\Preferences\Preferences::set('native_render_preview_enabled', $currentlyEnabled ? '0' : '1');
    }

    // Mirrors LoginPage.php's onLogin(): a "submit:login" NativeButton
    // collects both NativeTextField values client-side and sends them
    // along with the request (see NativeRenderPocActivity's
    // refetchWithFields()). Correct credentials set the real session key
    // the rest of the app already reads ($_SESSION['auth_user'],
    // HomePage.php/NativeHomeScreen both check it) and tell the client to
    // redirect; wrong ones just re-render this same screen with an error.
    $loginError = null;
    $redirectScreen = null;
    if ($screen === 'login' && $action === 'login') {
        $username = $_GET['username'] ?? '';
        $password = $_GET['password'] ?? '';
        if ($username === 'demo' && $password === 'demo') {
            $_SESSION['auth_user'] = $username;
            $redirectScreen = 'home';
        } else {
            $loginError = 'Identifiants invalides (essaie demo / demo).';
        }
    }
    // Logout stays on the home screen (matches HomePage.php's onLogout(),
    // which doesn't redirect either) — no client-side redirect needed,
    // the next line's rebuild of NativeHomeScreen just reflects the
    // now-cleared session directly.
    if ($action === 'logout') {
        unset($_SESSION['auth_user']);
    }

    // RenderDismissible's whole point: PHP never sees the swipe, only its
    // outcome, once NativeCanvasView.kt's own animation has already
    // finished — see NativeWidgetsDismissibleScreen. $_SESSION stands in
    // for "a real list backed by a database row"; a production screen
    // would DELETE a row here instead.
    if (!isset($_SESSION['dismissible_items'])) {
        $_SESSION['dismissible_items'] = array_combine(
            array_map(static fn (int $i): string => (string) $i, array_keys(\Engine\App\NativeWidgetsDismissibleScreen::initialItems())),
            \Engine\App\NativeWidgetsDismissibleScreen::initialItems(),
        );
    }
    if ($action !== null && str_starts_with($action, 'dismiss:')) {
        unset($_SESSION['dismissible_items'][substr($action, strlen('dismiss:'))]);
    }

    // RenderReorderable's whole point: PHP never sees the drag, only its
    // outcome — a comma-separated key order riding on the action string,
    // sent once the finger lifts and NativeCanvasView.kt's own settle
    // animation has already finished. See NativeWidgetsReorderScreen.
    if (!isset($_SESSION['reorder_items'])) {
        $_SESSION['reorder_items'] = \Engine\App\NativeWidgetsReorderScreen::initialItems();
    }
    if ($action !== null && str_starts_with($action, 'reorder:')) {
        $orderedIds = explode(',', substr($action, strlen('reorder:')));
        $current = $_SESSION['reorder_items'];
        $reordered = [];
        foreach ($orderedIds as $id) {
            if (isset($current[$id])) {
                $reordered[$id] = $current[$id];
            }
        }
        $_SESSION['reorder_items'] = $reordered;
    }

    // Mirrors WidgetsFirebaseAuthPage.php's onSignIn()/onSignUp() — a
    // plain server-side REST call to Firebase's Identity Toolkit API
    // (Engine\Firebase\FirebaseAuth, no client SDK/JS involved at all),
    // so this ports with no new native capability needed beyond what
    // NativeLoginScreen already proved (text fields + a session-backed
    // "who's signed in" flag).
    $firebaseError = null;
    if (in_array($action, ['signin', 'signup'], true)) {
        $webApiKey = $_ENV['FIREBASE_WEB_API_KEY'] ?? '';
        if ($webApiKey === '') {
            $firebaseError = "FIREBASE_WEB_API_KEY n'est pas configuré dans .env — voir phpnitro.yml.";
        } else {
            $email = trim($_GET['email'] ?? '');
            $password = $_GET['password'] ?? '';
            $result = $action === 'signin'
                ? \Engine\Firebase\FirebaseAuth::signIn($webApiKey, $email, $password)
                : \Engine\Firebase\FirebaseAuth::signUp($webApiKey, $email, $password);
            if ($result['error'] !== null) {
                $firebaseError = "Échec Firebase Auth : {$result['error']}";
            } else {
                $_SESSION['firebase_uid'] = $result['localId'];
            }
        }
    }
    if ($action === 'firebase_signout') {
        unset($_SESSION['firebase_uid']);
    }

    // Mirrors WidgetsStepperPage.php's Screen::$state (itself session-backed)
    // — the native pipeline has no per-request server object to hold step
    // state in, so it lives in $_SESSION directly, keyed the same "merge
    // this step's fields into the accumulated data, then move the step
    // pointer" way onStepperBack()/onStepperNext() do.
    $stepperStep = (int) ($_SESSION['widgets_stepper_step'] ?? 0);
    $stepperData = $_SESSION['widgets_stepper_data'] ?? [];
    if (in_array($action, ['stepper_next', 'stepper_back', 'stepper_reset'], true)) {
        if ($action === 'stepper_reset') {
            $stepperStep = 0;
            $stepperData = [];
        } else {
            foreach (['name', 'email', 'plan'] as $field) {
                if (isset($_GET[$field])) {
                    $stepperData[$field] = $_GET[$field];
                }
            }
            $stepperStep = $action === 'stepper_next' ? min($stepperStep + 1, 2) : max($stepperStep - 1, 0);
        }
        $_SESSION['widgets_stepper_step'] = $stepperStep;
        $_SESSION['widgets_stepper_data'] = $stepperData;
    }

    // Screen builders live in lib/pages/Native*.php — captures/ has
    // multiple reference screens, and this route just dispatches to
    // whichever one ?screen= asks for instead of growing one giant
    // function per screen added. 'home' — the real HomePage.php
    // conversion, not a reference-image recreation — is both the default
    // and the root NativeRenderPocActivity's screen stack starts from.
    $tree = match ($screen) {
        'otp' => \Engine\App\NativeOtpScreen::build($screenWidth, $screenHeight),
        'settings' => \Engine\App\NativeSettingsScreen::build($screenWidth, $screenHeight),
        'documents' => \Engine\App\NativeDocumentsScreen::build($screenWidth, $tapCount),
        'product' => \Engine\App\NativeProductScreen::build($screenWidth, $_GET['id'] ?? '?'),
        'login' => \Engine\App\NativeLoginScreen::build($screenWidth, $loginError),
        'device' => \Engine\App\NativeDeviceScreen::build($screenWidth, $screenHeight),
        'api' => \Engine\App\NativeApiScreen::build($screenWidth, $screenHeight),
        'widgets' => \Engine\App\NativeWidgetsIndexScreen::build($screenWidth, $screenHeight),
        'widgets-forms' => \Engine\App\NativeWidgetsFormsScreen::build($screenWidth),
        'widgets-layout' => \Engine\App\NativeWidgetsLayoutScreen::build($screenWidth, $screenHeight),
        'widgets-lazylist' => \Engine\App\NativeWidgetsLazyListScreen::build($screenWidth, $screenHeight, $scrollY),
        'widgets-dismissible' => \Engine\App\NativeWidgetsDismissibleScreen::build($screenWidth, $screenHeight, $_SESSION['dismissible_items']),
        'widgets-reorder' => \Engine\App\NativeWidgetsReorderScreen::build($screenWidth, $screenHeight, $_SESSION['reorder_items']),
        'widgets-dialogs' => \Engine\App\NativeWidgetsDialogsScreen::build($screenWidth, $screenHeight),
        'widgets-stepper' => \Engine\App\NativeWidgetsStepperScreen::build($screenWidth, $screenHeight, $stepperStep, $stepperData),
        'widgets-countries' => \Engine\App\NativeWidgetsCountriesScreen::build($screenWidth, $screenHeight),
        'widgets-media' => \Engine\App\NativeWidgetsMediaScreen::build($screenWidth, $screenHeight),
        'widgets-maps' => \Engine\App\NativeWidgetsMapsScreen::build($screenWidth, $screenHeight),
        'widgets-firebase-auth' => \Engine\App\NativeWidgetsFirebaseAuthScreen::build($screenWidth, $screenHeight, $firebaseError, $_GET['fb_mode'] ?? 'signin'),
        'widgets-lottie' => \Engine\App\NativeWidgetsLottieScreen::build($screenWidth, $screenHeight),
        'widgets-splash' => \Engine\App\NativeWidgetsSplashScreen::build($screenWidth, $screenHeight),
        'widgets-clienttabs' => \Engine\App\NativeWidgetsClientTabsScreen::build($screenWidth, $screenHeight),
        'widgets-async' => \Engine\App\NativeWidgetsAsyncScreen::build($screenWidth, $screenHeight),
        default => \Engine\App\NativeHomeScreen::build($screenWidth, $screenHeight),
    };

    $contentSize = $tree->layout(new Constraints($screenWidth, $screenWidth, 0, Constraints::INFINITY));

    $canvas = new NativeCanvas();
    $canvas->setContentHeight($contentSize->height);
    if ($screen === 'widgets-lazylist') {
        $canvas->setScrollFollow();
    }
    $tree->paint($canvas, 0, 0);
    $canvas->setRenderTimeMs((microtime(true) - $renderStart) * 1000);
    if ($redirectScreen !== null) {
        $canvas->setRedirect($redirectScreen);
    }

    // NativeRenderPocActivity sends back the hash of the last response it
    // actually applied (only for a same-screen refetch — see
    // NativeCanvas::stableHash()'s docblock). An identical hash means
    // nothing visible would change, so skip re-sending the whole payload
    // (and Kotlin skips re-parsing/redrawing it) — most valuable for
    // RenderLazyList's scroll-follow prefetch ticks and any action whose
    // outcome happens not to touch what's on screen.
    $hash = $canvas->stableHash();
    if (($_GET['lastHash'] ?? null) === $hash) {
        echo json_encode(['unchanged' => true, 'hash' => $hash]);
    } else {
        echo $canvas->toJson();
    }
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
        ['lib/pages', 'lib/backend/src', 'public'],
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

// No WebView content pages left (see the removal of
// SettingsPage.php/WidgetsIndexPage.php/WidgetsLayoutPage.php once their
// native conversions reached full parity) — every request past this
// point is either a stray link to a route that no longer exists, or a
// crawler/probe. NativeRenderPocActivity only ever talks to
// /native/layout-demo above.
renderNotFound();
