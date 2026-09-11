# PHP embed SAPI, vendored for iOS

`libphp.a` + the headers needed to link against it, committed as-is —
same convention `android/README.md` already documents for
`android/engine/src/main/jniLibs/<abi>/libphp.so`: cross-compiling PHP
from source needs a whole toolchain (bison ≥3.0, re2c, autoconf, the
iOS SDK) most contributors won't have set up, so the built artifact is
checked in rather than rebuilt on every `xcodebuild`.

Two architecture slices, one per iOS target this project builds for:

- `ios-arm64/` — real iPhone (`arm64-apple-ios15.0`)
- `ios-arm64-simulator/` — iOS Simulator on Apple Silicon (`arm64-apple-ios15.0-simulator`)

Both built from PHP `8.3.15` (`php/php-src` tag `php-8.3.15`), `--enable-embed=static`,
`--disable-all` (only the embed SAPI + the always-mandatory `ext/standard`/`Zend`/`TSRM`
core — no extensions beyond what PHP itself requires to build at all).

## Reproducing this build

Proven end-to-end first via 4 small, CI-observed iterations (see
`.github/workflows/ci.yml`'s `ios-php-embed-step1` through `step4` jobs
and their own commit history for exactly what each real failure was
and how it got fixed) before being run locally to produce these
committed artifacts. Two real, non-obvious fixes are required — skip
either and the build silently mis-cross-compiles or fails outright:

1. **bison**: macOS ships an ancient bison 2.3 (Apple keeps it outdated
   deliberately, GPLv3 licensing) — PHP's own `./configure` refuses
   anything older than 3.0. Install a real one and put it FIRST on
   `PATH` **before** running `./configure` (not just before `make` —
   `./configure` bakes the discovered bison path into the generated
   `Makefile`):
   ```sh
   brew install bison re2c
   export PATH="/opt/homebrew/opt/bison/bin:$PATH"
   ```

2. **`posix_spawn_file_actions_addchdir_np`**: `ext/standard/proc_open.c`
   calls this function when its own `configure`-time check
   (`ext/standard/config.m4`'s `PHP_CHECK_FUNC(posix_spawn_file_actions_addchdir_np)`)
   detects it as available — which it wrongly does on iOS: the SDK
   header declares it, but marks it `unavailable` for iOS deployment
   targets specifically, something a plain "is this symbol declared"
   autoconf check has no way to know. Setting the standard
   `ac_cv_func_posix_spawn_file_actions_addchdir_np=no` autoconf cache
   override does **NOT** work here — PHP's own `PHP_CHECK_FUNC` macro
   (`build/php.m4`) explicitly `unset`s that exact cache variable
   before testing, discarding any pre-seeded value on purpose. The only
   fix is removing the probe from the source, applied fresh on each
   checkout (never vendored as a permanent php-src patch, since this
   directory only keeps the *build output*, not php-src itself) —
   **before** `./buildconf`, not after (`buildconf` regenerates
   `./configure` from the `.m4` sources, so patching afterward has no
   effect on the already-generated script):
   ```sh
   sed -i '' '/PHP_CHECK_FUNC(posix_spawn_file_actions_addchdir_np)/d' ext/standard/config.m4
   ```

Device build:

```sh
git clone --depth 1 --branch php-8.3.15 https://github.com/php/php-src.git
cd php-src
sed -i '' '/PHP_CHECK_FUNC(posix_spawn_file_actions_addchdir_np)/d' ext/standard/config.m4
export PATH="/opt/homebrew/opt/bison/bin:$PATH"
./buildconf --force
export IOS_SDK=$(xcrun --sdk iphoneos --show-sdk-path)
export CC="xcrun -sdk iphoneos clang -arch arm64"
export CXX="xcrun -sdk iphoneos clang++ -arch arm64"
export CFLAGS="-isysroot $IOS_SDK -mios-version-min=15.0"
export CXXFLAGS="-isysroot $IOS_SDK -mios-version-min=15.0"
export LDFLAGS="-isysroot $IOS_SDK -mios-version-min=15.0"
./configure --host=aarch64-apple-darwin --disable-all --without-pear \
  --disable-cli --disable-cgi --disable-phpdbg --enable-embed=static
make -j"$(sysctl -n hw.ncpu)"
```

Simulator build: same steps, swap every `iphoneos`/`-mios-version-min`
for `iphonesimulator`/`-mios-simulator-version-min`.

Headers committed here are copied straight out of that build tree —
`main/`, `Zend/`, `TSRM/`, `sapi/embed/` (`*.h` only, ~3.8MB per
target) — everything `#include`d transitively from `sapi/embed/php_embed.h`.
Consumers need all four of `main/`, `Zend/`, `TSRM/` and this
directory itself on the include search path (headers `#include` each
other by bare filename, e.g. `"zend.h"`, not `"Zend/zend.h"`):

```
-I<target>/include -I<target>/include/main -I<target>/include/Zend -I<target>/include/TSRM
```

`libtool`'s own build already links in what PHP's `sapi/embed` needs
beyond libc — `-lresolv -liconv -lm` at the consumer's link step is
still required (these are system libraries the static archive doesn't
bundle, same as android/README.md's own `libsqlite3.so` needing to sit
alongside `libphp.so`, not inside it).
