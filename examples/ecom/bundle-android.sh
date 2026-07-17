#!/usr/bin/env bash
# Bundles this example's public/ + lib/ into its dedicated Android app (same
# mechanism as the root framework's `phpx bundle:android`).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_ROOT="$(cd "$ROOT/../.." && pwd)"
STAGING="$ROOT/android/app/src/main/assets/www"
ANDROID_RES="$ROOT/android/app/src/main/res"

# Native app label + launcher icon, driven by phpnitro.yml — this example
# has no bin/phpx of its own, so plain grep/sed reads its handful of
# top-level scalar keys (same idiom already used above for .env) instead of
# a full YAML parser.
APP_NAME=$(grep -m1 '^name:' "$ROOT/phpnitro.yml" | sed -E 's/^name:[[:space:]]*"?([^"]*)"?[[:space:]]*$/\1/')
if [ -n "$APP_NAME" ] && [ -f "$ANDROID_RES/values/strings.xml" ]; then
  sed -i "s|<string name=\"app_name\">.*</string>|<string name=\"app_name\">${APP_NAME}</string>|" "$ANDROID_RES/values/strings.xml"
fi

ICON_PATH=$(grep -m1 '^icon:' "$ROOT/phpnitro.yml" | sed -E 's/^icon:[[:space:]]*"?([^"]*)"?[[:space:]]*$/\1/')
if [ -n "$ICON_PATH" ]; then
  ICON_BG=$(grep -m1 '^icon_background:' "$ROOT/phpnitro.yml" | sed -E 's/^icon_background:[[:space:]]*"?([^"]*)"?[[:space:]]*$/\1/')
  php "$FRAMEWORK_ROOT/bin/generate-android-icon.php" "$ROOT/$ICON_PATH" "$ANDROID_RES" "${ICON_BG:-#FFFFFF}"
fi

rm -rf "$STAGING"
mkdir -p "$STAGING/lib/pages" "$STAGING/lib/backend" "$STAGING/packages/ui" "$STAGING/packages/database" "$STAGING/packages/payments" "$STAGING/packages/maps" "$STAGING/packages/dialogs"

cp -r "$ROOT/public" "$STAGING/public"
cp -r "$ROOT/assets" "$STAGING/assets"
cp -r "$ROOT/lib/pages/app" "$STAGING/lib/pages/app"
cp -r "$ROOT/lib/backend/src" "$STAGING/lib/backend/src"
cp -r "$FRAMEWORK_ROOT/packages/ui/src" "$STAGING/packages/ui/src"
cp -r "$FRAMEWORK_ROOT/packages/database/src" "$STAGING/packages/database/src"
cp -r "$FRAMEWORK_ROOT/packages/payments/src" "$STAGING/packages/payments/src"
cp -r "$FRAMEWORK_ROOT/packages/maps/src" "$STAGING/packages/maps/src"
cp -r "$FRAMEWORK_ROOT/packages/dialogs/src" "$STAGING/packages/dialogs/src"
cp "$ROOT/composer.json" "$STAGING/composer.json"

# examples/ecom's composer.json reaches the shared framework packages via
# "../../packages/*/src" (two levels up, out into the monorepo's packages/
# directory) — the staged bundle copies those packages in directly as
# siblings instead, so the autoload paths need rewriting to match.
sed -i 's#\.\./\.\./packages/ui/src/#packages/ui/src/#' "$STAGING/composer.json"
sed -i 's#\.\./\.\./packages/database/src/#packages/database/src/#' "$STAGING/composer.json"
sed -i 's#\.\./\.\./packages/payments/src/#packages/payments/src/#' "$STAGING/composer.json"
sed -i 's#\.\./\.\./packages/maps/src/#packages/maps/src/#' "$STAGING/composer.json"
sed -i 's#\.\./\.\./packages/dialogs/src/#packages/dialogs/src/#' "$STAGING/composer.json"

# "env" without the dot: AAPT drops hidden files from assets. Force
# APP_DEBUG=false in the shipped bundle regardless of the dev .env.
if grep -q '^APP_DEBUG=' "$ROOT/.env"; then
  sed 's/^APP_DEBUG=.*/APP_DEBUG=false/' "$ROOT/.env" > "$STAGING/env"
else
  cp "$ROOT/.env" "$STAGING/env"
  echo "APP_DEBUG=false" >> "$STAGING/env"
fi

composer install --no-dev --no-interaction --quiet --working-dir="$STAGING"
rm -f "$STAGING/composer.lock"

echo "Bundled into $STAGING"
