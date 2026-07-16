#!/usr/bin/env bash
# Smoke test for bin/phpx — runs each command for real (creates a temp
# project, starts a real server, hits real routes) and fails loudly on the
# first problem. Not a mock: if this passes, `phpx` genuinely works.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORKDIR="$(mktemp -d)"
trap 'pkill -f "phpx serve 8123" 2>/dev/null || true; rm -rf "$WORKDIR"' EXIT

echo "== phpx new =="
(cd "$WORKDIR" && php "$ROOT/bin/phpx" new demo-app)
for dir in lib packages android ios assets bin; do
  test -d "$WORKDIR/demo-app/$dir" || { echo "FAIL: $dir/ missing from scaffold"; exit 1; }
done
test -f "$WORKDIR/demo-app/.env" || { echo "FAIL: .env missing from scaffold"; exit 1; }
echo "OK"

echo "== composer install (ui + backend) =="
composer install --working-dir="$WORKDIR/demo-app/lib/ui" --quiet
composer install --working-dir="$WORKDIR/demo-app/lib/backend" --quiet
echo "OK"

echo "== phpx make:screen =="
(cd "$WORKDIR/demo-app" && php bin/phpx make:screen About)
test -f "$WORKDIR/demo-app/lib/ui/app/AboutPage.php" || { echo "FAIL: AboutPage.php not created"; exit 1; }
grep -q "AboutPage" "$WORKDIR/demo-app/lib/ui/public/index.php" || { echo "FAIL: route not registered"; exit 1; }
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
pkill -f "phpx serve 8123" 2>/dev/null || true
echo "OK"

echo "== phpx bundle:android =="
(cd "$WORKDIR/demo-app" && php bin/phpx bundle:android)
test -d "$WORKDIR/demo-app/android/app/src/main/assets/www/lib/ui/public" || { echo "FAIL: ui/ not bundled"; exit 1; }
test -d "$WORKDIR/demo-app/android/app/src/main/assets/www/lib/backend/vendor" || { echo "FAIL: backend/ not bundled"; exit 1; }
test -f "$WORKDIR/demo-app/android/app/src/main/assets/www/env" || { echo "FAIL: env not bundled"; exit 1; }
echo "OK"

echo
echo "All phpx smoke tests passed."
