#!/usr/bin/env bash
# Bundles this example's lib/pages/ + lib/backend/ into its dedicated Android
# app (same mechanism as the root framework's `phpx bundle:android`).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_ROOT="$(cd "$ROOT/../.." && pwd)"
STAGING="$ROOT/android/app/src/main/assets/www"

rm -rf "$STAGING"
mkdir -p "$STAGING/lib/pages" "$STAGING/lib/backend"

# composer.lock is deliberately NOT copied: it would pin the path
# repository's dist reference to examples/ecom's own (deeper) directory
# depth, which stops resolving once composer.json's URL is rewritten below
# for the staging bundle's flatter layout.
for entry in app public composer.json; do
  if [ -d "$ROOT/lib/pages/$entry" ]; then
    cp -r "$ROOT/lib/pages/$entry" "$STAGING/lib/pages/$entry"
  elif [ -f "$ROOT/lib/pages/$entry" ]; then
    cp "$ROOT/lib/pages/$entry" "$STAGING/lib/pages/$entry"
  fi
done

for entry in src bootstrap.php composer.json; do
  if [ -d "$ROOT/lib/backend/$entry" ]; then
    cp -r "$ROOT/lib/backend/$entry" "$STAGING/lib/backend/$entry"
  elif [ -f "$ROOT/lib/backend/$entry" ]; then
    cp "$ROOT/lib/backend/$entry" "$STAGING/lib/backend/$entry"
  fi
done

# The widget SDK and database packages themselves, copied as real files (not
# the symlink Composer's path repository normally uses) — Android's
# AssetManager can't follow symlinks when the bundle is later packaged into
# the APK.
for package in ui database; do
  mkdir -p "$STAGING/packages/$package"
  cp -r "$FRAMEWORK_ROOT/packages/$package/src" "$STAGING/packages/$package/src"
  cp "$FRAMEWORK_ROOT/packages/$package/composer.json" "$STAGING/packages/$package/composer.json"
done

# The staged bundle is flatter than examples/ecom's own tree (lib/ and
# packages/ end up as direct siblings, same shape as the root framework's own
# checkout) — the path repository URLs, copied verbatim from a composer.json
# written for the deeper examples/ecom/lib/{pages,backend} nesting, need
# rewriting to match.
sed -i 's#\.\./\.\./\.\./\.\./packages/ui#../../packages/ui#' "$STAGING/lib/pages/composer.json"
sed -i 's#\.\./\.\./\.\./\.\./packages/database#../../packages/database#' "$STAGING/lib/backend/composer.json"

# "env" without the dot: AAPT drops hidden files from assets. Force
# APP_DEBUG=false in the shipped bundle regardless of the dev .env.
if grep -q '^APP_DEBUG=' "$ROOT/.env"; then
  sed 's/^APP_DEBUG=.*/APP_DEBUG=false/' "$ROOT/.env" > "$STAGING/env"
else
  cp "$ROOT/.env" "$STAGING/env"
  echo "APP_DEBUG=false" >> "$STAGING/env"
fi

composer install --no-dev --no-interaction --quiet --working-dir="$STAGING/lib/pages"
composer install --no-dev --no-interaction --quiet --working-dir="$STAGING/lib/backend"
rm -f "$STAGING/lib/pages/composer.lock" "$STAGING/lib/backend/composer.lock"

# Composer's path repositories install phpnitro/ui and phpnitro/database as
# symlinks — replace them with real copies (Android's AssetManager can't
# follow symlinks).
for symlink in "$STAGING/lib/pages/vendor/phpnitro/ui" "$STAGING/lib/backend/vendor/phpnitro/database"; do
  if [ -L "$symlink" ]; then
    real_target="$(readlink -f "$symlink")"
    rm "$symlink"
    cp -r "$real_target" "$symlink"
  fi
done

echo "Bundled lib/pages/ + lib/backend/ into $STAGING"
