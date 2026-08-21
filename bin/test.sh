#!/usr/bin/env bash
# Smoke test for bin/phpx — runs each command for real (creates a temp
# project, starts a real server, hits real routes) and fails loudly on the
# first problem. Not a mock: if this passes, `phpx` genuinely works.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORKDIR="$(mktemp -d)"
trap 'pkill -f "phpx serve 8123" 2>/dev/null || true; pkill -f "php -S 127.0.0.1:8123" 2>/dev/null || true; rm -rf "$WORKDIR"' EXIT

echo "== phpx new =="
(cd "$WORKDIR" && php "$ROOT/bin/phpx" new demo-app)
for dir in lib android ios assets public; do
  test -d "$WORKDIR/demo-app/$dir" || { echo "FAIL: $dir/ missing from scaffold"; exit 1; }
done
# packages/ and bin/ are NOT copied — phpnitro/ui and friends are real
# Packagist dependencies (resolved into vendor/), and phpx is meant to be
# installed once globally, not duplicated into every project (see
# cmdNew()'s own comment on this). A scaffold shipping either would be a
# regression back to copying framework internals.
test ! -d "$WORKDIR/demo-app/packages" || { echo "FAIL: packages/ should NOT be copied into a scaffold — phpnitro/ui etc. are Packagist dependencies now"; exit 1; }
test ! -d "$WORKDIR/demo-app/bin" || { echo "FAIL: bin/ should NOT be copied into a scaffold — phpx is installed globally"; exit 1; }
test ! -d "$WORKDIR/demo-app/android/engine" || { echo "FAIL: android/engine/ should NOT be copied into a scaffold — com.github.phpnitro:android-engine is a JitPack dependency now"; exit 1; }
test -f "$WORKDIR/demo-app/.env" || { echo "FAIL: .env missing from scaffold"; exit 1; }
test -f "$WORKDIR/demo-app/composer.json" || { echo "FAIL: composer.json missing from scaffold"; exit 1; }
grep -q '"phpnitro/ui"' "$WORKDIR/demo-app/composer.json" || { echo "FAIL: composer.json should declare phpnitro/ui as a dependency"; exit 1; }
# A blank project has no database, no Firebase project, no country picker
# yet — these are opt-in `composer require phpnitro/<name>` additions, not
# bundled upfront (see newProjectComposerJson()'s own comment).
for optional in database firebase countries preferences format; do
  grep -q "\"phpnitro/${optional}\"" "$WORKDIR/demo-app/composer.json" && { echo "FAIL: composer.json should NOT declare phpnitro/${optional} by default"; exit 1; }
done
true
grep -q 'com.github.phpnitro:android-engine' "$WORKDIR/demo-app/android/app/build.gradle.kts" || { echo "FAIL: android/app/build.gradle.kts should depend on com.github.phpnitro:android-engine"; exit 1; }
# Exactly one real starter screen, not a blank folder — the same "you get
# a running app on the first try" promise `flutter create`'s default
# counter app makes (see nativeHomeScreenTemplate()), just not a dozen
# demo screens.
test "$(ls "$WORKDIR/demo-app/lib/pages"/*.php 2>/dev/null | wc -l)" -eq 1 || { echo "FAIL: lib/pages/ should ship exactly one starter screen for new projects"; exit 1; }
test -f "$WORKDIR/demo-app/lib/pages/NativeHomeScreen.php" || { echo "FAIL: lib/pages/NativeHomeScreen.php missing"; exit 1; }
test -z "$(ls "$WORKDIR/demo-app/lib/backend/src/Controller"/*.php 2>/dev/null)" || { echo "FAIL: lib/backend/src/Controller/ should ship empty for new projects"; exit 1; }
test ! -d "$WORKDIR/demo-app/assets/audio" || { echo "FAIL: assets/audio/ (demo-only) should not ship for new projects"; exit 1; }
# linux/macos/windows are opt-in via --all (see below) — a plain `phpx new`
# stays an Android + iOS project, not a 5-platform one nobody asked for.
for dir in linux macos windows; do
  test ! -d "$WORKDIR/demo-app/$dir" || { echo "FAIL: $dir/ should NOT be copied into a scaffold without --all"; exit 1; }
done
echo "OK"

echo "== phpx new --all =="
(cd "$WORKDIR" && php "$ROOT/bin/phpx" new demo-app-all --all)
for dir in lib android ios linux macos windows assets public; do
  test -d "$WORKDIR/demo-app-all/$dir" || { echo "FAIL: $dir/ missing from --all scaffold"; exit 1; }
done
test -f "$WORKDIR/demo-app-all/linux/phpnitro_desktop/app.py" || { echo "FAIL: linux/phpnitro_desktop/app.py missing from --all scaffold"; exit 1; }
test -f "$WORKDIR/demo-app-all/macos/Package.swift" || { echo "FAIL: macos/Package.swift missing from --all scaffold"; exit 1; }
test -f "$WORKDIR/demo-app-all/windows/PhpNitroDesktop.Protocol/PhpNitroDesktop.Protocol.csproj" || { echo "FAIL: windows/PhpNitroDesktop.Protocol/*.csproj missing from --all scaffold"; exit 1; }
echo "OK"

echo "== composer install (single root vendor) =="
composer install --working-dir="$WORKDIR/demo-app" --quiet
test -d "$WORKDIR/demo-app/vendor" || { echo "FAIL: no vendor/ created"; exit 1; }
echo "OK"

echo "== phpx make:page (root) =="
(cd "$WORKDIR/demo-app" && php "$ROOT/bin/phpx" make:page Home)
test -f "$WORKDIR/demo-app/lib/pages/HomePage.php" || { echo "FAIL: HomePage.php not created"; exit 1; }
echo "OK"

echo "== phpx make:page =="
# No file gets edited to "register" this — public/index.php and Kernel.php
# discover pages/controllers straight from the filesystem at request time
# (Engine\AutoRouter), so there's nothing to grep for here beyond the
# created files themselves; the == phpx serve == section below proves the
# discovery actually works end-to-end.
(cd "$WORKDIR/demo-app" && php "$ROOT/bin/phpx" make:page About)
test -f "$WORKDIR/demo-app/lib/pages/AboutPage.php" || { echo "FAIL: AboutPage.php not created"; exit 1; }
test -f "$WORKDIR/demo-app/lib/backend/src/Controller/AboutController.php" || { echo "FAIL: paired AboutController.php not created"; exit 1; }
echo "OK"

echo "== phpx make:entity =="
(cd "$WORKDIR/demo-app" && php "$ROOT/bin/phpx" make:entity Product)
test -f "$WORKDIR/demo-app/lib/backend/src/Entity/Product.php" || { echo "FAIL: Product.php not created"; exit 1; }
test -f "$WORKDIR/demo-app/lib/backend/src/Repository/ProductRepository.php" || { echo "FAIL: paired ProductRepository.php not created"; exit 1; }
echo "OK"

echo "== phpx serve (real HTTP requests) =="
# The framework ships no JS/CSS assets of its own anymore (see git history:
# "remove Tailwind CSS and its JS assets" — the native render engine reaches
# the device through Kotlin/Canvas, not <script> tags), and a fresh scaffold's
# assets/ is intentionally empty (resetPagesForNewProject() strips the demo
# audio fixture) — so there is nothing pre-existing to serve. Writing one
# fixture file here still proves syncAssets() + router.php's static-file
# passthrough genuinely work, not just that the directory exists.
mkdir -p "$WORKDIR/demo-app/assets/test-fixture"
echo "ok" > "$WORKDIR/demo-app/assets/test-fixture/marker.txt"
(cd "$WORKDIR/demo-app" && php "$ROOT/bin/phpx" serve 8123 >/tmp/phpx-test-server.log 2>&1) &
for _ in $(seq 1 20); do
  curl -sf http://127.0.0.1:8123/api >/dev/null 2>&1 && break
  sleep 0.3
done
# No WebView pages are left (see public/index.php's own renderNotFound()
# docblock) — GET / is expected to 404; the app's real entry point is the
# native render engine's /native/layout-demo (default screen = home).
curl -sf -o /dev/null http://127.0.0.1:8123/native/layout-demo || { echo "FAIL: default native screen (/native/layout-demo) did not respond"; exit 1; }
# /api itself is generated by the earlier "make:page Home" call ("home" is
# hardcoded to apiRoute "/api" instead of "/api/home" — see cmdMakePage()),
# not a built-in endpoint a fresh scaffold ships on its own — a project with
# no pages yet has no backend routes either.
curl -sf http://127.0.0.1:8123/api | grep -q '"message"' || { echo "FAIL: /api (generated HomeController) did not respond"; exit 1; }
curl -sf -o /dev/null "http://127.0.0.1:8123/native/layout-demo?screen=about" || { echo "FAIL: generated 'about' screen (native render route) did not respond"; exit 1; }
curl -sf http://127.0.0.1:8123/api/about | grep -q '"message"' || { echo "FAIL: generated /api/about (paired controller) did not respond"; exit 1; }
curl -sf http://127.0.0.1:8123/assets/test-fixture/marker.txt | grep -q '^ok$' || { echo "FAIL: static file under assets/ not served"; exit 1; }
pkill -f "phpx serve 8123" 2>/dev/null || true
pkill -f "php -S 127.0.0.1:8123" 2>/dev/null || true
echo "OK"

echo "== phpx bundle:android =="
(cd "$WORKDIR/demo-app" && php "$ROOT/bin/phpx" bundle:android)
test -d "$WORKDIR/demo-app/android/app/src/main/assets/www/public" || { echo "FAIL: public/ not bundled"; exit 1; }
test -d "$WORKDIR/demo-app/android/app/src/main/assets/www/vendor" || { echo "FAIL: vendor/ not bundled"; exit 1; }
# phpnitro/ui et al. now come from vendor/ (Packagist), not a copied
# packages/ directory — this is bundle:android's own composer install
# actually resolving them, not phpx new's scaffold step.
test -d "$WORKDIR/demo-app/android/app/src/main/assets/www/vendor/phpnitro/ui" || { echo "FAIL: vendor/phpnitro/ui not bundled"; exit 1; }
test -f "$WORKDIR/demo-app/android/app/src/main/assets/www/env" || { echo "FAIL: env not bundled"; exit 1; }
test -z "$(find "$WORKDIR/demo-app/android/app/src/main/assets/www" -type l)" || { echo "FAIL: bundle should contain no symlinks (Android's AssetManager can't follow them)"; exit 1; }
# Every bundled .php file (this project's own lib/pages, lib/backend/src,
# public/ — minified, see copyDirectory()'s minifyPhp param — AND
# vendor/, composer-installed fresh and untouched by it) must still be
# syntactically valid: a real, not merely smaller, safety net against a
# minifier bug shipping broken PHP to a device.
while IFS= read -r -d '' phpFile; do
  php -l "$phpFile" > /dev/null || { echo "FAIL: bundled file has a syntax error: $phpFile"; exit 1; }
done < <(find "$WORKDIR/demo-app/android/app/src/main/assets/www" -name "*.php" -print0)
grep -q '/\*\*' "$WORKDIR/demo-app/android/app/src/main/assets/www/public/index.php" && { echo "FAIL: bundled public/index.php still has a docblock — minification isn't running"; exit 1; }
echo "OK"

echo
echo "All phpx smoke tests passed."
