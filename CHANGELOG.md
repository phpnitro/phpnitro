# Changelog

All notable changes to this project are documented here. The format loosely
follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning
is informal while the project is pre-1.0 (see `phpnitro.yml`'s `version`).

## Unreleased

### Added
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
- `ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md` updated with real device-verification
  notes and a Kivy-specific gaps section.
