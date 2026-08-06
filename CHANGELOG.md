# Changelog

All notable changes to this project are documented here. The format loosely
follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning
is informal while the project is pre-1.0 (see `phpnitro.yml`'s `version`).

## Unreleased

### Added
- Typed style params on 7 more widgets: `AppBar` (background), `Dropdown`
  (background/foreground), `LocationButton` (background/foreground),
  `IconButton` (foreground), `Divider` (color), `Stepper` (activeColor,
  follows `Theme::primary()` by default like other themed widgets). Also
  fixed `ProgressBar::$barColor`, previously a raw string despite being
  listed as "typed" — now a real `?Color`.
- `docs/proposals/editeur-visuel.md` — design notes on a possible drag & drop
  visual builder (why Flutter itself never shipped one, three approaches with
  their trade-offs). Proposal only, not implemented.
- `Engine\AnimatedContainer` and `Engine\Hero` — FLIP-technique tween between two
  server renders and Flutter-Hero-style shared element flight, on top of a new
  `phpx:beforeSwap` event in `nav.js`. Respects `prefers-reduced-motion`.
- `phpx docs:api` — generates `docs/api/*.md` (one page per package) from every
  public class's docblocks and real method signatures via Reflection, plus an
  index at `docs/api.md`. Regenerate after any public API change; never hand-edit.
- `docs/tutorials/` — two step-by-step guides (building a feature end-to-end,
  wiring a real social sign-in button).
- GitHub issue/PR templates (`.github/ISSUE_TEMPLATE/`, `PULL_REQUEST_TEMPLATE.md`).
- Device services: `Sensors` (accelerometer/gyroscope/magnetic field), `Torch`,
  `Brightness`, `Battery`, `DeviceId`, `Bluetooth` (bonded devices), `SecureStorage`
  (Android Keystore-backed), `Contacts` and `CalendarEvents` (read-only),
  `BackgroundTask` (periodic WorkManager ping).
- `Engine\SocialAuth`: service-based OAuth2 (Authorization Code flow) for Google,
  Microsoft, GitHub, Facebook, Slack, X (PKCE), and Apple (ES256 client-secret JWT) —
  attach `onClick()` to any existing button/image, no pre-built widgets.
- `Engine\Countries`: offline country/city/continent/capital/flag data.
- New packages: `Preferences` (SQLite key-value), `Connectivity`, `Launcher`,
  `Diagnostics` (crash reporting), `Format` (number/currency/date, no `ext-intl`).
- New UI widgets: `Stack`, `Positioned`, `Wrap`, `FadeIn` (+ `Curves`), `Rounded`,
  `AnimatedText`, `AutoSizeText`, `InfiniteScrollList`, `LottieView`, `Canvas`
  (basic CustomPaint-equivalent: rect/circle/line/text ops replayed once at mount).
  `Icon` expanded from 11 to 36 hand-built SVG icons.
- `Button` gained typed `background`/`foreground` `Color` params (hover shade
  derived automatically), matching `Text`/`Container`'s existing typed style API.
- `phpx build:android [debug|release]` — one-command bundle + `gradle assemble`,
  auto-installing a pinned Gradle into `~/.phpx/tools` if none compatible is on PATH.
- Android release signing: `signingConfigs`/`keystore.properties` wiring, R8/ProGuard
  enabled for release builds (`proguard-rules.pro` keeps `@JavascriptInterface` members).
- A basic DevTools overlay (`APP_DEBUG=true` only): current route, render time,
  peak memory, PHP version, toggled via a floating button.
- Deep linking (`phpnitro://`), external link/tel/mailto hand-off in the WebView,
  dynamic app icon switching (`activity-alias`).
- `LICENSE` (MIT) and copyright headers across `packages/*/src`.

### Fixed
- **No outbound HTTPS ever worked from the on-device PHP binary** — the
  cross-compiled `php-ndk` build has neither `curl` nor `openssl`, confirmed by
  running the actual binary on a real device. Not Feexpay-specific: this broke
  every `file_get_contents('https://...')` call in the framework (Stripe,
  OAuth, Firebase) that hadn't yet been exercised on-device. Fixed by
  cross-compiling OpenSSL statically into the binary (`android/php-ndk-patch/`)
  and bundling Mozilla's CA store (`assets/cacert.pem`) since Android has no
  OpenSSL-readable system trust store of its own. Verified against Feexpay's
  real API (`HTTP 200`/`201`) on an Infinix X6532, then confirmed with a real
  end-to-end mobile money transaction (real account, real phone number, real
  USSD confirmation, real MTN MoMo debit SMS) all the way to the app's own
  order confirmation screen.
- `Engine\Payments\Feexpay` rewritten to call the REST API directly via
  streams instead of the `feexpay/feexpay-php` vendor SDK, which calls `curl_*`
  directly and could never have worked on-device regardless of the fix above.
  Also fixed: Feexpay rejects a non-integer `amount` ("amount must be an
  integer number"), only visible once the HTTPS call itself stopped failing
  first — now rounded explicitly before sending.
- Microphone recording (`getUserMedia` unreliably failing on-device with
  "Could not start audio source") — now uses a native `MediaRecorder` capture
  path by default, with the old browser API kept only as a fallback.
- WebView permission prompts (`onPermissionRequest`) now check the actual
  Android runtime permission state before granting, and trigger the real OS
  permission dialog on demand instead of silently failing.
- `FloatingActionButton` losing its fixed bottom-corner position after any
  navigation — caused by the page-transition animation's `transform` creating
  a new containing block; the transition is opacity-only now.
- A visible flicker on every button action (not just navigation) — the page-enter
  animation wrapper is now only applied on a real route change.
- `cmdBundleAndroid()` used a hardcoded per-package copy list; every package
  added after it was last updated silently failed to autoload on-device. Now
  discovers packages via `glob('packages/*/src')`.
- `phpx` commands other than `new` implicitly assumed `bin/phpx` lived inside
  the project being operated on. `PHPX_ROOT` now always resolves to the
  current working directory (like `composer`/`artisan`); `PHPX_TOOL_ROOT`
  (this checkout's own location) is used only by `phpx new` to source its
  scaffold template — so a single globally-installed `phpx` works against
  whichever project you're standing in.

### Changed
- README rewritten as a short, professional pitch; detailed reference material
  moved to `docs/` (getting started, widgets, device/native, payments,
  integrations, CLI, mobile builds, architecture).
