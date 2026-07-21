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
for dir in lib packages android ios assets bin public; do
  test -d "$WORKDIR/demo-app/$dir" || { echo "FAIL: $dir/ missing from scaffold"; exit 1; }
done
test -f "$WORKDIR/demo-app/.env" || { echo "FAIL: .env missing from scaffold"; exit 1; }
test -f "$WORKDIR/demo-app/composer.json" || { echo "FAIL: composer.json missing from scaffold"; exit 1; }
test -z "$(ls "$WORKDIR/demo-app/lib/pages/app"/*.php 2>/dev/null)" || { echo "FAIL: lib/pages/app/ should ship empty (no demo pages) for new projects"; exit 1; }
echo "OK"

echo "== composer install (single root vendor) =="
composer install --working-dir="$WORKDIR/demo-app" --quiet
test -d "$WORKDIR/demo-app/vendor" || { echo "FAIL: no vendor/ created"; exit 1; }
echo "OK"

echo "== phpx make:page (root) =="
(cd "$WORKDIR/demo-app" && php bin/phpx make:page Home /)
test -f "$WORKDIR/demo-app/lib/pages/app/HomePage.php" || { echo "FAIL: HomePage.php not created"; exit 1; }
echo "OK"

echo "== phpx make:page =="
(cd "$WORKDIR/demo-app" && php bin/phpx make:page About)
test -f "$WORKDIR/demo-app/lib/pages/app/AboutPage.php" || { echo "FAIL: AboutPage.php not created"; exit 1; }
grep -q "AboutPage" "$WORKDIR/demo-app/public/index.php" || { echo "FAIL: page route not registered"; exit 1; }
test -f "$WORKDIR/demo-app/lib/backend/src/Controller/AboutController.php" || { echo "FAIL: paired AboutController.php not created"; exit 1; }
grep -q "AboutController" "$WORKDIR/demo-app/lib/backend/src/Kernel.php" || { echo "FAIL: paired controller route not registered"; exit 1; }
echo "OK"

echo "== phpx make:entity =="
(cd "$WORKDIR/demo-app" && php bin/phpx make:entity Product)
test -f "$WORKDIR/demo-app/lib/backend/src/Entity/Product.php" || { echo "FAIL: Product.php not created"; exit 1; }
test -f "$WORKDIR/demo-app/lib/backend/src/Repository/ProductRepository.php" || { echo "FAIL: paired ProductRepository.php not created"; exit 1; }
echo "OK"

echo "== phpx serve (real HTTP requests) =="
(cd "$WORKDIR/demo-app" && php bin/phpx serve 8123 >/tmp/phpx-test-server.log 2>&1) &
for _ in $(seq 1 20); do
  curl -sf http://127.0.0.1:8123/ >/dev/null 2>&1 && break
  sleep 0.3
done
curl -sf -o /dev/null http://127.0.0.1:8123/ || { echo "FAIL: GET / did not respond"; exit 1; }
curl -sf http://127.0.0.1:8123/api/hello | grep -q '"message"' || { echo "FAIL: /api/hello (unified backend) did not respond"; exit 1; }
curl -sf -o /dev/null http://127.0.0.1:8123/about || { echo "FAIL: generated /about route did not respond"; exit 1; }
curl -sf http://127.0.0.1:8123/api/about | grep -q '"message"' || { echo "FAIL: generated /api/about (paired controller) did not respond"; exit 1; }
curl -sf -o /dev/null http://127.0.0.1:8123/assets/js/gestures.js || { echo "FAIL: assets/js/gestures.js not served"; exit 1; }
pkill -f "phpx serve 8123" 2>/dev/null || true
pkill -f "php -S 127.0.0.1:8123" 2>/dev/null || true
echo "OK"

echo "== phpx bundle:android =="
(cd "$WORKDIR/demo-app" && php bin/phpx bundle:android)
test -d "$WORKDIR/demo-app/android/app/src/main/assets/www/public" || { echo "FAIL: public/ not bundled"; exit 1; }
test -d "$WORKDIR/demo-app/android/app/src/main/assets/www/vendor" || { echo "FAIL: vendor/ not bundled"; exit 1; }
test -f "$WORKDIR/demo-app/android/app/src/main/assets/www/env" || { echo "FAIL: env not bundled"; exit 1; }
test -z "$(find "$WORKDIR/demo-app/android/app/src/main/assets/www" -type l)" || { echo "FAIL: bundle should contain no symlinks (Android's AssetManager can't follow them)"; exit 1; }
# Every bundled .php file (framework source, minified — see
# copyDirectory()'s minifyPhp param — AND vendor/, composer-installed
# fresh and untouched by it) must still be syntactically valid: a real,
# not merely smaller, safety net against a minifier bug shipping broken
# PHP to a device.
while IFS= read -r -d '' phpFile; do
  php -l "$phpFile" > /dev/null || { echo "FAIL: bundled file has a syntax error: $phpFile"; exit 1; }
done < <(find "$WORKDIR/demo-app/android/app/src/main/assets/www" -name "*.php" -print0)
grep -q '/\*\*' "$WORKDIR/demo-app/android/app/src/main/assets/www/packages/ui/src/PageRenderer.php" && { echo "FAIL: bundled PageRenderer.php still has a docblock — minification isn't running"; exit 1; }
echo "OK"

echo
echo "All phpx smoke tests passed."
