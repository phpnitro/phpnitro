#!/usr/bin/env bash
# Bundles this example's ui/ + backend/ into its dedicated Android app
# (same mechanism as the root framework's `phpx bundle:android`).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STAGING="$ROOT/android/app/src/main/assets/www"

rm -rf "$STAGING"
mkdir -p "$STAGING/ui" "$STAGING/backend"

for entry in app php public composer.json composer.lock; do
  if [ -d "$ROOT/ui/$entry" ]; then
    cp -r "$ROOT/ui/$entry" "$STAGING/ui/$entry"
  else
    cp "$ROOT/ui/$entry" "$STAGING/ui/$entry"
  fi
done

for entry in src composer.json composer.lock; do
  if [ -d "$ROOT/backend/$entry" ]; then
    cp -r "$ROOT/backend/$entry" "$STAGING/backend/$entry"
  else
    cp "$ROOT/backend/$entry" "$STAGING/backend/$entry"
  fi
done

# "env" without the dot: AAPT drops hidden files from assets. Force
# APP_DEBUG=false in the shipped bundle regardless of the dev .env.
if grep -q '^APP_DEBUG=' "$ROOT/.env"; then
  sed 's/^APP_DEBUG=.*/APP_DEBUG=false/' "$ROOT/.env" > "$STAGING/env"
else
  cp "$ROOT/.env" "$STAGING/env"
  echo "APP_DEBUG=false" >> "$STAGING/env"
fi

composer install --no-dev --no-interaction --quiet --working-dir="$STAGING/ui"
composer install --no-dev --no-interaction --quiet --working-dir="$STAGING/backend"
rm -f "$STAGING/ui/composer.lock" "$STAGING/backend/composer.lock"

echo "Bundled ui/ + backend/ into $STAGING"
