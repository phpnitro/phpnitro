<?php

require __DIR__ . '/../vendor/autoload.php';

use Engine\Database\Database;
use Engine\Native\Constraints;
use Engine\Native\Canvas;
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
// point (no more separate lib/backend/bootstrap.php). phpnitro/database is
// an opt-in Composer dependency now (added via `composer require
// phpnitro/database` the moment a project actually needs a Repository), not
// bundled into every scaffold by default, so this is a no-op until then.
if (class_exists(Database::class)) {
    Database::useSqlitePath(__DIR__ . '/../lib/backend/var/data.sqlite');
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
    // The top-level handler installed above always emits an HTML page —
    // fine for a browser hitting this by accident, useless for
    // NativeRenderPocActivity, which only ever speaks JSON. Without this,
    // an uncaught exception ANYWHERE below (a screen's build(), a
    // Repository call, MediaQuery::init()...) would still get caught by
    // that HTML handler, but the Content-Type header set one line above
    // is already locked in — the client would receive an HTML body
    // labeled "application/json", fail to parse it (a plain
    // JSONException, silently logged — see NativeCanvasView.kt's
    // setCommands()), and just show whatever was already on screen with
    // zero indication anything went wrong. This handler REPLACES the
    // HTML one for the rest of this request (this route always exit;s at
    // its end, so there's no "restore the old handler" concern) and
    // gives NativeRenderPocActivity's fetchDrawCommands() a real
    // `{"error": {...}}` shape to detect and show its own error card
    // for — see that method's own handling and showScreenErrorOverlay().
    // Full file/line/trace only in debug mode, same gating the HTML
    // handler above already uses — a production build shouldn't leak
    // filesystem paths or internal call structure to whoever's
    // network-adjacent.
    set_exception_handler(static function (\Throwable $e) use ($debug): void {
        http_response_code(500);
        $error = ['class' => $e::class, 'message' => $e->getMessage()];
        if ($debug) {
            $error['file'] = $e->getFile();
            $error['line'] = $e->getLine();
            $error['trace'] = $e->getTraceAsString();
        }
        echo json_encode(['error' => $error], JSON_THROW_ON_ERROR);
    });
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
    // Follows the device's real system dark-mode setting by default (see
    // NativeRenderPocActivity.kt's own dark param, read from
    // Configuration.uiMode) — same "system, not a separate in-app toggle
    // you have to remember to set" default Flutter/RN apps ship with.
    \Engine\Native\Tokens::init(($_GET['dark'] ?? '0') === '1');
    // Follows the device's real system language by default (see
    // NativeRenderPocActivity.kt's own locale param) — falls back to
    // 'fr' (this framework's own baseline locale, see lib/lang/fr.php's
    // docblock) for anything unrecognized rather than a locale with no
    // translation file at all.
    \Engine\I18n\Translator::init($_GET['locale'] ?? 'fr', __DIR__ . '/../lib/lang');
    $screen = $_GET['screen'] ?? 'home';
    $action = $_GET['action'] ?? null;
    // LazyList's windowed prefetch — see NativeCanvasView.kt's
    // checkScrollFollow(). Every screen receives this; only ones that
    // build a LazyList actually read it.
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
    // GestureDetector's counter area.
    if ($action === 'home_decrement') {
        \Engine\Preferences\Preferences::set('native_home_counter', (string) ((int) \Engine\Preferences\Preferences::get('native_home_counter', '0') - 1));
    }

    // Pull-to-refresh demo (NativeWidgetsFormsScreen, see
    // Canvas::setPullToRefresh() below) — a real timestamp so the
    // "Dernière actualisation" line actually changes on every pull,
    // proving the round-trip happened rather than just re-showing the
    // same static screen.
    if ($action === 'widgets_pull_refresh') {
        $_SESSION['widgets_last_refresh'] = time();
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

    // Mirrors LoginPage.php's onLogin(): a "submit:login" Button
    // collects both TextField values client-side and sends them
    // along with the request (see NativeRenderPocActivity's
    // refetchWithFields()). Correct credentials set the real session key
    // the rest of the app already reads ($_SESSION['auth_user'],
    // HomePage.php/NativeHomeScreen both check it) and tell the client to
    // redirect; wrong ones just re-render this same screen with an error.
    // Real password_hash()/password_verify() now, via UserRepository —
    // not the hardcoded string comparison this used to be. "demo"/"demo"
    // still works (UserRepository seeds that account on a fresh DB), it
    // just goes through the real check like any other account now.
    $loginError = null;
    $redirectScreen = null;
    if ($screen === 'login' && $action === 'login') {
        $username = $_GET['username'] ?? '';
        $password = $_GET['password'] ?? '';
        $user = (new \Backend\Repository\UserRepository())->verifyCredentials($username, $password);
        if ($user !== null) {
            $_SESSION['auth_user'] = $user['username'];
            $_SESSION['auth_user_id'] = $user['id'];
            $redirectScreen = 'home';
        } else {
            $loginError = 'Identifiants invalides.';
        }
    }

    // NativeRegisterScreen's counterpart — validated server-side (client
    // TextFields don't enforce anything), then auto-signs-in on success
    // exactly like a fresh login would, so a new account lands straight
    // on the home screen instead of having to log in a second time.
    $registerError = null;
    if ($screen === 'register' && $action === 'register') {
        $username = trim($_GET['username'] ?? '');
        $password = $_GET['password'] ?? '';
        $passwordConfirm = $_GET['password_confirm'] ?? '';
        $users = new \Backend\Repository\UserRepository();
        if ($username === '' || $password === '') {
            $registerError = 'Utilisateur et mot de passe requis.';
        } elseif (strlen($password) < 6) {
            $registerError = 'Le mot de passe doit faire au moins 6 caractères.';
        } elseif ($password !== $passwordConfirm) {
            $registerError = 'Les mots de passe ne correspondent pas.';
        } elseif ($users->usernameTaken($username)) {
            $registerError = 'Ce nom d\'utilisateur est déjà pris.';
        } else {
            $user = $users->create($username, $password);
            $_SESSION['auth_user'] = $user['username'];
            $_SESSION['auth_user_id'] = $user['id'];
            $redirectScreen = 'home';
        }
    }
    // Forgot/reset password — see PasswordResetRepository's own docblock
    // for why the "sent" link is just shown on screen (no mailer
    // configured anywhere in this framework by default). The success
    // message is identical whether or not $username matched a real
    // account — only $devResetLink differs — so this handler can't be
    // used to enumerate valid usernames by watching for a different
    // response.
    $forgotPasswordError = null;
    $devResetLink = null;
    if ($screen === 'forgot-password' && $action === 'forgot_password') {
        $username = trim($_GET['username'] ?? '');
        if ($username === '') {
            $forgotPasswordError = "Nom d'utilisateur requis.";
        } else {
            $user = (new \Backend\Repository\UserRepository())->findByUsername($username);
            if ($user !== null) {
                $rawToken = (new \Backend\Repository\PasswordResetRepository())->createToken($user['id']);
                $devResetLink = $rawToken;
            }
            // $user === null falls through with $devResetLink still null
            // — the screen shows nothing extra, same as a real "check your
            // inbox" message would for an unknown address, deliberately
            // indistinguishable from the success case above.
        }
    }

    $resetPasswordError = null;
    $resetPasswordSuccess = null;
    if ($screen === 'reset-password' && $action === 'reset_password') {
        $token = trim($_GET['reset_token'] ?? '');
        $newPassword = $_GET['new_password'] ?? '';
        $newPasswordConfirm = $_GET['new_password_confirm'] ?? '';
        $resets = new \Backend\Repository\PasswordResetRepository();
        if ($token === '' || $newPassword === '') {
            $resetPasswordError = 'Code et nouveau mot de passe requis.';
        } elseif (strlen($newPassword) < 6) {
            $resetPasswordError = 'Le mot de passe doit faire au moins 6 caractères.';
        } elseif ($newPassword !== $newPasswordConfirm) {
            $resetPasswordError = 'Les mots de passe ne correspondent pas.';
        } else {
            $userId = $resets->findValidUserId($token);
            if ($userId === null) {
                $resetPasswordError = 'Code invalide ou expiré.';
            } else {
                (new \Backend\Repository\UserRepository())->updatePassword($userId, $newPassword);
                $resets->markUsed($token);
                $resetPasswordSuccess = 'Mot de passe mis à jour — tu peux te connecter.';
            }
        }
    }

    // Logout stays on the home screen (matches HomePage.php's onLogout(),
    // which doesn't redirect either) — no client-side redirect needed,
    // the next line's rebuild of NativeHomeScreen just reflects the
    // now-cleared session directly.
    if ($action === 'logout') {
        unset($_SESSION['auth_user']);
    }

    // Dismissible's whole point: PHP never sees the swipe, only its
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

    // Reorderable's whole point: PHP never sees the drag, only its
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

    // Google Sign-In — the ID token itself was already obtained on-device
    // via Android's Credential Manager (see NativeDeviceBridge.kt's
    // signInWithGoogle(), dispatched from handleDeviceAction("googlesignin")
    // in NativeRenderPocActivity.kt) and arrives here as a plain field,
    // the same "device capability reports its result, then a normal
    // refetch" shape every other device: capability already uses. This
    // just exchanges that token for a Firebase session — see
    // FirebaseAuth::signInWithGoogleIdToken()'s own docblock for why that
    // single call is Firebase's whole job here.
    if ($action === 'google_signin') {
        $webApiKey = $_ENV['FIREBASE_WEB_API_KEY'] ?? '';
        $googleIdToken = $_GET['google_id_token'] ?? '';
        if ($webApiKey === '') {
            $firebaseError = "FIREBASE_WEB_API_KEY n'est pas configuré dans .env — voir phpnitro.yml.";
        } elseif ($googleIdToken === '') {
            $firebaseError = $_GET['google_signin_error'] ?? 'Connexion Google annulée ou indisponible.';
        } else {
            $result = \Engine\Firebase\FirebaseAuth::signInWithGoogleIdToken($webApiKey, $googleIdToken);
            if ($result['error'] !== null) {
                $firebaseError = "Échec Google Sign-In : {$result['error']}";
            } else {
                $_SESSION['firebase_uid'] = $result['localId'];
            }
        }
    }

    // GitHub/Facebook/Microsoft/Apple — real Engine\SocialAuth\ classes
    // (a standard OAuth2 Authorization Code flow, see OAuthProvider's own
    // docblock), not a native SDK — none of these four have one this
    // framework bundles, so a Custom Tab redirect is the actual
    // native-appropriate flow, same idea as Google's Credential Manager
    // just without a first-party SDK to call. $oauthState is generated
    // fresh below (see the match($screen) dispatch further down, right
    // before NativeWidgetsFirebaseAuthScreen::build() is called) every
    // time that screen renders, and MUST be verified here against
    // whatever the redirect brings back — this is the actual CSRF check,
    // not decoration; Kotlin's handleOAuthCallback() only transports the
    // value, it never validates it.
    $socialAuthError = null;
    if ($action !== null && str_starts_with($action, 'oauth_callback:')) {
        $provider = substr($action, strlen('oauth_callback:'));
        $providerClass = match ($provider) {
            'github' => \Engine\SocialAuth\GithubSignIn::class,
            'facebook' => \Engine\SocialAuth\FacebookSignIn::class,
            'microsoft' => \Engine\SocialAuth\MicrosoftSignIn::class,
            'apple' => \Engine\SocialAuth\AppleSignIn::class,
            default => null,
        };
        $code = $_GET['oauth_code'] ?? '';
        $state = $_GET['oauth_state'] ?? '';
        $expectedState = $_SESSION['oauth_state'] ?? null;
        unset($_SESSION['oauth_state']); // one-shot, whether this attempt succeeds or not

        if ($providerClass === null) {
            $socialAuthError = "Fournisseur inconnu : {$provider}.";
        } elseif ($state === '' || $expectedState === null || !hash_equals($expectedState, $state)) {
            $socialAuthError = 'Échec de vérification de sécurité — réessaie.';
        } elseif ($code === '') {
            $socialAuthError = $_GET['oauth_error'] ?? 'Connexion annulée.';
        } else {
            $envPrefix = strtoupper($provider);
            $clientId = $_ENV["{$envPrefix}_CLIENT_ID"] ?? '';
            $redirectUri = 'phpnitro://oauth-callback';
            if ($clientId === '') {
                $socialAuthError = "{$envPrefix}_CLIENT_ID n'est pas configuré dans .env.";
            } elseif ($provider === 'apple') {
                $teamId = $_ENV['APPLE_TEAM_ID'] ?? '';
                $keyId = $_ENV['APPLE_KEY_ID'] ?? '';
                $privateKeyPath = $_ENV['APPLE_PRIVATE_KEY_PATH'] ?? '';
                if ($teamId === '' || $keyId === '' || $privateKeyPath === '') {
                    $socialAuthError = 'APPLE_TEAM_ID / APPLE_KEY_ID / APPLE_PRIVATE_KEY_PATH ne sont pas configurés dans .env.';
                } else {
                    $clientSecret = \Engine\SocialAuth\AppleSignIn::clientSecret($teamId, $keyId, $clientId, $privateKeyPath);
                    $profile = \Engine\SocialAuth\AppleSignIn::exchangeCode($code, $clientId, $clientSecret, $redirectUri);
                    if ($profile === null) {
                        $socialAuthError = 'Échec de connexion Apple.';
                    } else {
                        $_SESSION['social_user'] = ['provider' => 'apple', ...$profile];
                    }
                }
            } else {
                $clientSecret = $_ENV["{$envPrefix}_CLIENT_SECRET"] ?? '';
                if ($clientSecret === '') {
                    $socialAuthError = "{$envPrefix}_CLIENT_SECRET n'est pas configuré dans .env.";
                } else {
                    $profile = $providerClass::exchangeCode($code, $clientId, $clientSecret, $redirectUri);
                    if ($profile === null) {
                        $socialAuthError = "Échec de connexion {$provider}.";
                    } else {
                        $_SESSION['social_user'] = ['provider' => $provider, ...$profile];
                    }
                }
            }
        }
    }
    if ($action === 'social_signout') {
        unset($_SESSION['social_user']);
    }

    // Feexpay mobile-money checkout — see NativeWidgetsPaymentsScreen's
    // own docblock and docs/payments.md's security note. $_SESSION only
    // ever holds the CURRENT reference (a pointer), never the payment's
    // actual status — that lives in OrderRepository's real DB row, which
    // is the only thing check_status is allowed to trust.
    $paymentError = null;
    $orders = new \Backend\Repository\OrderRepository();
    $currentReference = $_SESSION['payment_reference'] ?? null;
    if ($screen === 'widgets-payments' && $action === 'pay') {
        $shopId = $_ENV['FEEXPAY_SHOP_ID'] ?? '';
        $apiKey = $_ENV['FEEXPAY_API_KEY'] ?? '';
        $phone = trim($_GET['pay_phone'] ?? '');
        $network = $_GET['pay_network'] ?? '';
        if ($shopId === '' || $apiKey === '') {
            $paymentError = "FEEXPAY_SHOP_ID / FEEXPAY_API_KEY ne sont pas configurés dans .env — voir phpnitro.yml.";
        } elseif ($phone === '' || $network === '') {
            $paymentError = 'Numéro et réseau requis.';
        } else {
            $reference = uniqid('order_', true);
            $amount = \Engine\App\NativeWidgetsPaymentsScreen::AMOUNT_XOF;
            $result = \Engine\Payments\Feexpay::payLocal($shopId, $apiKey, (float) $amount, $phone, $network, 'PhpNitro Demo', '', $reference);
            if ($result === false) {
                $paymentError = 'Échec du déclenchement du paiement (réseau ou identifiants invalides).';
            } else {
                $orders->create($reference, $amount, $phone, $network);
                $_SESSION['payment_reference'] = $reference;
                $currentReference = $reference;
            }
        }
    }
    if ($action === 'check_status' && $currentReference !== null) {
        $shopId = $_ENV['FEEXPAY_SHOP_ID'] ?? '';
        $apiKey = $_ENV['FEEXPAY_API_KEY'] ?? '';
        $status = \Engine\Payments\Feexpay::status($shopId, $apiKey, $currentReference);
        if ($status !== false && $status['status'] !== null) {
            $orders->updateStatus($currentReference, $status['status']);
        }
    }
    $currentOrder = $currentReference !== null ? $orders->find($currentReference) : null;

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

    // Fresh state + authorize URLs on every render of this screen — the
    // user can only ever tap whatever's CURRENTLY on screen, so
    // regenerating on every visit (rather than once) is correct, not
    // wasteful: $_SESSION['oauth_state'] always matches the button that's
    // actually visible. null (not an empty-string URL) when a provider's
    // client_id isn't configured — that's what tells
    // NativeRenderPocActivity's "oauth:" dispatch to fail informatively
    // instead of opening a Custom Tab to a doomed request.
    $githubAuthorizeUrl = null;
    $facebookAuthorizeUrl = null;
    if ($screen === 'widgets-firebase-auth') {
        $oauthState = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $oauthState;
        $redirectUri = 'phpnitro://oauth-callback';
        $githubClientId = $_ENV['GITHUB_CLIENT_ID'] ?? '';
        $facebookClientId = $_ENV['FACEBOOK_CLIENT_ID'] ?? '';
        if ($githubClientId !== '') {
            $githubAuthorizeUrl = \Engine\SocialAuth\GithubSignIn::authorizeUrl($githubClientId, $redirectUri) . '&state=' . $oauthState;
        }
        if ($facebookClientId !== '') {
            $facebookAuthorizeUrl = \Engine\SocialAuth\FacebookSignIn::authorizeUrl($facebookClientId, $redirectUri) . '&state=' . $oauthState;
        }
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
        'product' => \Engine\App\NativeProductScreen::build($screenWidth, $_GET['id'] ?? '?', $_GET['tab'] ?? null),
        'login' => \Engine\App\NativeLoginScreen::build($screenWidth, $loginError),
        'register' => \Engine\App\NativeRegisterScreen::build($screenWidth, $registerError),
        'forgot-password' => \Engine\App\NativeForgotPasswordScreen::build($screenWidth, $forgotPasswordError, $devResetLink),
        'reset-password' => \Engine\App\NativeResetPasswordScreen::build($screenWidth, $resetPasswordError, $resetPasswordSuccess),
        'device' => \Engine\App\NativeDeviceScreen::build($screenWidth, $screenHeight),
        'api' => \Engine\App\NativeApiScreen::build($screenWidth, $screenHeight),
        'widgets' => \Engine\App\NativeWidgetsIndexScreen::build($screenWidth, $screenHeight),
        'widgets-forms' => \Engine\App\NativeWidgetsFormsScreen::build($screenWidth, $_SESSION['widgets_last_refresh'] ?? null),
        'widgets-layout' => \Engine\App\NativeWidgetsLayoutScreen::build($screenWidth, $screenHeight),
        'widgets-lazylist' => \Engine\App\NativeWidgetsLazyListScreen::build($screenWidth, $screenHeight, $scrollY),
        'widgets-dismissible' => \Engine\App\NativeWidgetsDismissibleScreen::build($screenWidth, $screenHeight, $_SESSION['dismissible_items']),
        'widgets-reorder' => \Engine\App\NativeWidgetsReorderScreen::build($screenWidth, $screenHeight, $_SESSION['reorder_items']),
        'widgets-dialogs' => \Engine\App\NativeWidgetsDialogsScreen::build($screenWidth, $screenHeight),
        'widgets-stepper' => \Engine\App\NativeWidgetsStepperScreen::build($screenWidth, $screenHeight, $stepperStep, $stepperData),
        'widgets-countries' => \Engine\App\NativeWidgetsCountriesScreen::build($screenWidth, $screenHeight),
        'widgets-media' => \Engine\App\NativeWidgetsMediaScreen::build($screenWidth, $screenHeight),
        'widgets-maps' => \Engine\App\NativeWidgetsMapsScreen::build($screenWidth, $screenHeight),
        'widgets-firebase-auth' => \Engine\App\NativeWidgetsFirebaseAuthScreen::build($screenWidth, $screenHeight, $firebaseError ?? $socialAuthError ?? ($_GET['oauth_error'] ?? null), $_GET['fb_mode'] ?? 'signin', $githubAuthorizeUrl, $facebookAuthorizeUrl),
        'widgets-payments' => \Engine\App\NativeWidgetsPaymentsScreen::build($screenWidth, $screenHeight, $paymentError, $currentOrder),
        'widgets-lottie' => \Engine\App\NativeWidgetsLottieScreen::build($screenWidth, $screenHeight),
        'widgets-splash' => \Engine\App\NativeWidgetsSplashScreen::build($screenWidth, $screenHeight),
        'widgets-clienttabs' => \Engine\App\NativeWidgetsClientTabsScreen::build($screenWidth, $screenHeight),
        'widgets-async' => \Engine\App\NativeWidgetsAsyncScreen::build($screenWidth, $screenHeight),
        default => \Engine\App\NativeHomeScreen::build($screenWidth, $screenHeight),
    };

    $contentSize = $tree->layout(new Constraints($screenWidth, $screenWidth, 0, Constraints::INFINITY));

    $canvas = new Canvas();
    $canvas->setContentHeight($contentSize->height);
    if ($screen === 'widgets-lazylist') {
        $canvas->setScrollFollow();
    }
    if ($screen === 'widgets-forms') {
        $canvas->setPullToRefresh('widgets_pull_refresh');
    }
    $tree->paint($canvas, 0, 0);
    $canvas->setRenderTimeMs((microtime(true) - $renderStart) * 1000);
    if ($redirectScreen !== null) {
        $canvas->setRedirect($redirectScreen);
    }

    // Default transition by action shape — a push reads as "going
    // deeper" (slideLeft), a pop as "coming back" (slideRight); a tab
    // switch keeps the plain fade (it's a lateral move, not a stack
    // push/pop). Kotlin only actually applies this during a real
    // navigation crossfade (see setCommands()'s isNavigation branch);
    // a same-screen refetch never crossfades at all, so this is a
    // no-op there regardless of what it's set to. Any screen can
    // still override with its own setTransition() call after this.
    if ($action !== null && str_starts_with($action, 'navigate:')) {
        $canvas->setTransition('slideLeft');
    } elseif ($action === 'back') {
        $canvas->setTransition('slideRight');
    }

    // NativeRenderPocActivity sends back the hash of the last response it
    // actually applied (only for a same-screen refetch — see
    // Canvas::stableHash()'s docblock). An identical hash means
    // nothing visible would change, so skip re-sending the whole payload
    // (and Kotlin skips re-parsing/redrawing it) — most valuable for
    // LazyList's scroll-follow prefetch ticks and any action whose
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
