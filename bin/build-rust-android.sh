#!/usr/bin/env bash
# Cross-compiles rust/phpnitro-render for Android (arm64-v8a + armeabi-v7a)
# and drops the resulting .so files into android/engine/src/main/jniLibs/ —
# the exact same command .github/workflows/ci.yml's android-build job runs
# (see that job's own comment on why this .so is never committed to git),
# just runnable locally in one shot instead of by hand.
#
# Only needed when developing THIS monorepo's own android/engine/ — a
# scaffolded `phpx new` project consumes android-engine as a precompiled
# JitPack artifact instead (see android/README.md), so this deliberately
# is NOT a `phpx` subcommand: phpx serves apps that CONSUME the framework,
# this serves people building the framework itself.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if ! rustup target list --installed | grep -q '^aarch64-linux-android$'; then
  echo "==> rustup target add aarch64-linux-android armv7-linux-androideabi"
  rustup target add aarch64-linux-android armv7-linux-androideabi
fi

if ! command -v cargo-ndk >/dev/null 2>&1; then
  echo "==> cargo install cargo-ndk --locked"
  cargo install cargo-ndk --locked
fi

if [ -z "${ANDROID_NDK_HOME:-}" ]; then
  # Same "usual places" bin/phpx's own resolveOrInstallAndroidSdk() checks
  # for the SDK itself — the NDK lives one level under the SDK root, in a
  # version-numbered directory (e.g. ndk/30.0.14904198), so the newest one
  # present is picked via a version sort.
  for sdk_root in "${ANDROID_HOME:-}" "${ANDROID_SDK_ROOT:-}" "$HOME/Android/Sdk" "$HOME/Library/Android/sdk"; do
    [ -n "$sdk_root" ] && [ -d "$sdk_root/ndk" ] || continue
    ndk_version="$(ls "$sdk_root/ndk" | sort -V | tail -n1)"
    [ -z "$ndk_version" ] && continue
    export ANDROID_NDK_HOME="$sdk_root/ndk/$ndk_version"
    break
  done
fi

if [ -z "${ANDROID_NDK_HOME:-}" ] || [ ! -d "$ANDROID_NDK_HOME" ]; then
  echo 'error: ANDROID_NDK_HOME not set and no NDK found under $ANDROID_HOME/ndk — install it via the SDK Manager (sdkmanager --install "ndk;<version>") or export ANDROID_NDK_HOME yourself.' >&2
  exit 1
fi

echo "==> Using NDK at $ANDROID_NDK_HOME"
echo "==> cargo ndk -t arm64-v8a -t armeabi-v7a build --release"
(
  cd "$ROOT/rust/phpnitro-render"
  cargo ndk -t arm64-v8a -t armeabi-v7a -o "$ROOT/android/engine/src/main/jniLibs" build --release
)

echo "==> Done — libphpnitro_render.so is now in android/engine/src/main/jniLibs/{arm64-v8a,armeabi-v7a}/"
