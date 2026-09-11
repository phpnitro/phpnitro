# PHP embed SAPI, vendored for iOS

`libphp.xcframework` — the two architecture slices this project needs
(real device + Simulator) packaged into one `.xcframework`, the
standard SPM/Xcode mechanism for "same API, different binary per
platform variant", consumed directly as a `.binaryTarget` from
`Package.swift`. Committed as-is, same convention `android/README.md`
already documents for `android/engine/src/main/jniLibs/<abi>/libphp.so`:
cross-compiling PHP from source needs a whole toolchain (bison ≥3.0,
re2c, autoconf, the iOS SDK) most contributors won't have set up, so
the built artifact is checked in rather than rebuilt on every
`xcodebuild`.

Two architecture slices, one per iOS target this project builds for:

- device (`arm64-apple-ios15.0`) — a real iPhone
- simulator (`arm64-apple-ios15.0-simulator`) — iOS Simulator on Apple Silicon

Both built from PHP `8.4.2` (`php/php-src` tag `php-8.4.2`) — matching
Android's own cross-compiled `libphp.so` exactly (see
`android/php-ndk-patch/Dockerfile`'s own `ARG PHP_VERSION=8.4.2`), for
a real reason discovered the hard way, not for consistency's own sake:
this project's own `composer.json` uses Symfony 8.x, whose
`Request.php` genuinely uses PHP 8.4's **property hooks**
(`public ParameterBag $attributes { set { ... } } }`) — a real language
construct the parser itself rejects on anything older, not merely a
declared `composer.json` floor (`--ignore-platform-req=php` in `phpx
bundle:ios`/`bundle:android` bypasses THAT, but can't do anything about
actual syntax the target interpreter can't parse). Confirmed on a real
physical iPhone: this xcframework was originally built as PHP 8.3.15
(the exact version that got the whole embed-SAPI proof-of-concept
working first, see git history), and the very first screen touching
`Symfony\Component\HttpFoundation\Request` crashed with `Parse error:
syntax error, unexpected token "{"` pointing straight at that
property-hook declaration — Android never hit this because it was
8.4.2 from the start.

`--enable-embed=static`, `--disable-all` plus a short list of
extensions the real app actually needs on top of
`ext/standard`/`Zend`/`TSRM`'s always-mandatory core — see "Runtime extensions" below.

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
   calls this function when its own `configure`-time check detects it
   as available — which it wrongly does on iOS: the SDK header declares
   it, but marks it `unavailable` for iOS deployment targets
   specifically, something a plain "is this symbol declared" autoconf
   check has no way to know. Setting the standard
   `ac_cv_func_posix_spawn_file_actions_addchdir_np=no` autoconf cache
   override does **NOT** work here — PHP's own `PHP_CHECK_FUNC`/
   `AC_CHECK_FUNCS` machinery discards any pre-seeded value on purpose.
   The only fix is removing the probe from the source, applied fresh on
   each checkout (never vendored as a permanent php-src patch, since
   this directory only keeps the *build output*, not php-src itself) —
   **before** `./buildconf`, not after (`buildconf` regenerates
   `./configure` from the `.m4` sources, so patching afterward has no
   effect on the already-generated script). **The exact line changed
   between PHP 8.3 and 8.4** — 8.3's `ext/standard/config.m4` called
   `PHP_CHECK_FUNC(posix_spawn_file_actions_addchdir_np)` as its own
   standalone line (a plain line-delete sufficed); 8.4 batched it into
   `AC_CHECK_FUNCS([posix_spawn_file_actions_addchdir_np elf_aux_info])`
   instead (confirmed the hard way: reusing 8.3's `sed` verbatim against
   8.4's tree matched nothing, silently no-opped, and reproduced the
   exact original compile error against `proc_open.c`) — check
   `ext/standard/config.m4` itself first if this ever needs redoing
   against a newer PHP tag, rather than assuming either form:
   ```sh
   sed -i '' 's/AC_CHECK_FUNCS(\[posix_spawn_file_actions_addchdir_np elf_aux_info\])/AC_CHECK_FUNCS([elf_aux_info])/' ext/standard/config.m4
   ```

Device build:

```sh
git clone --depth 1 --branch php-8.4.2 https://github.com/php/php-src.git
cd php-src
sed -i '' 's/AC_CHECK_FUNCS(\[posix_spawn_file_actions_addchdir_np elf_aux_info\])/AC_CHECK_FUNCS([elf_aux_info])/' ext/standard/config.m4
export PATH="/opt/homebrew/opt/bison/bin:$PATH"
./buildconf --force
export IOS_SDK=$(xcrun --sdk iphoneos --show-sdk-path)
export CC="xcrun -sdk iphoneos clang -arch arm64"
export CXX="xcrun -sdk iphoneos clang++ -arch arm64"
export CFLAGS="-isysroot $IOS_SDK -mios-version-min=15.0"
export CXXFLAGS="-isysroot $IOS_SDK -mios-version-min=15.0"
export LDFLAGS="-isysroot $IOS_SDK -mios-version-min=15.0"
export SQLITE_CFLAGS="-I$IOS_SDK/usr/include"
export SQLITE_LIBS="-L$IOS_SDK/usr/lib -lsqlite3"
./configure --host=aarch64-apple-darwin --disable-all --without-pear \
  --disable-cli --disable-cgi --disable-phpdbg --enable-embed=static \
  --without-pcre-jit --enable-session=static \
  --enable-pdo=static --with-pdo-sqlite=static --with-sqlite3=static \
  --enable-filter=static \
  --enable-mbstring=static --disable-mbregex
make -j"$(sysctl -n hw.ncpu)"
```

Simulator build: same steps, swap every `iphoneos`/`-mios-version-min`
for `iphonesimulator`/`-mios-simulator-version-min` (and `IOS_SDK`'s own
`--sdk` accordingly — `SQLITE_CFLAGS`/`SQLITE_LIBS` must point at
whichever SDK is actually being targeted).

## Runtime extensions: not just the bare embed SAPI

`--disable-all` alone produces a runtime too bare for the real app —
each of the flags below was added after a **real** crash or error
reproducing the actual app's `public/index.php` on-device (never
guessed up front):

- **`--without-pcre-jit`**: PCRE2's JIT tries to `mmap` executable
  memory at runtime (`sljit_malloc_exec`) the moment any `preg_match()`
  runs — forbidden for third-party apps on iOS, and not caught at
  build time at all. Confirmed via an `lldb` backtrace on a real
  Simulator crash (Bus error, signal 10) pinpointing
  `sljit_malloc_exec` ← `php_pcre2_jit_compile` ← `preg_match`.
  Without this flag, the FIRST regex evaluated anywhere in the app's
  request path (Symfony's router, Doctrine, etc.) hard-crashes the
  process.
- **`--enable-session=static`**: `--disable-all` also drops the session
  extension — `session_start()` (called by the real app) is undefined
  without it, a real `Call to undefined function` error, not
  speculative.
- **`--enable-pdo=static --with-pdo-sqlite=static --with-sqlite3=static`**:
  Doctrine DBAL's SQLite driver needs the `PDO` class — without these,
  a real `Class "PDO" not found` error the first time the app touches
  its database. Cross-compiling `sqlite3` support hits its own
  pkg-config failure (`configure: error: ... pkg-config script could
  not be found or is too old`) since pkg-config can't detect the iOS
  SDK's system `libsqlite3` when cross-compiling — worked around by
  setting `SQLITE_CFLAGS`/`SQLITE_LIBS` explicitly (see the configure
  invocation above) instead of relying on pkg-config auto-detection.
- **`--enable-filter=static`**: `ext/filter` (`filter_var()`, the
  `FILTER_*` constants) is what `--disable-all` disables least
  obviously — Symfony's own `ParameterBag` (`http-foundation`, used by
  every `Backend\Kernel` request) calls `filter_var(..., FILTER_VALIDATE_INT)`
  internally for its typed getters, so `Undefined constant
  "FILTER_VALIDATE_INT"` surfaces the FIRST time ANY code touches a
  `Request`/`ParameterBag`, not something exotic. Confirmed on
  Simulator reproducing this app's own "Backend" screen
  (`NativeApiScreen.php`, a real `Backend\Kernel::handle()` in-process
  call). Never hit on Android for a structural reason, not luck: its
  own build (`android/php-ndk-patch/Dockerfile`) never starts from
  `--disable-all` at all — it disables a short explicit list
  (dom/simplexml/xml/xmlreader/xmlwriter/phar/phpdbg) and keeps
  everything else, `ext/filter` included, at its normal default-enabled
  state.
- **`--enable-mbstring=static --disable-mbregex`**: `packages/countries`,
  `packages/format`, `packages/ui` genuinely call `mb_chr()`/
  `mb_str_split()`/`mb_strlen()`/`mb_strtolower()`/`mb_substr()` — found
  by proactively grepping the real bundled code for other
  extension-gated functions after the `ext/filter` miss above, not from
  a crash yet. Plain `--enable-mbstring=static` fails to configure on
  its own: the multibyte-*regex* half (`mb_ereg*`, never called by this
  app's own code) needs `oniguruma`, and cross-compiling hits the exact
  same pkg-config-can't-see-the-iOS-SDK failure `sqlite3` did above —
  `--disable-mbregex` removes that dependency entirely instead of
  vendoring `oniguruma` for a feature nothing here calls.

**Known gap, deliberately not addressed here**: `curl_*`
(`packages/payments/src/Feexpay.php`), `openssl_*`
(`packages/firebase`, `packages/socialauth`), and `Intl*`
(`packages/format`) are also real calls in bundled code this xcframework
can't satisfy yet — unlike `filter`/`mbstring`, these need actual
third-party static libraries cross-compiled for iOS first (`libcurl`,
OpenSSL, ICU's data files), the same scale of work
`android/php-ndk-patch/Dockerfile`'s own OpenSSL 3.0.15 static build
already had to do for Android (see `android/README.md`'s own
CURLOPT_POST/openssl wrapper story) — not a `configure` flag away.
Whichever of those three a screen touches first will surface its own
`Undefined function`/`Undefined constant` error, same shape as the two
above, until someone does that vendoring work for iOS too.

Consumers must link `-lsqlite3` alongside the already-required
`-lresolv -liconv -lm` (see `Package.swift`'s own `CPhpEmbed` target)
now that `ext/pdo_sqlite`/`ext/sqlite3` are statically enabled — the
static archive itself doesn't bundle it, same split as those three.

## Headers: flattened, not the raw build-tree layout

The headers actually needed are everything `#include`d transitively
from `sapi/embed/php_embed.h` — `main/*.h`, `Zend/*.h`, `TSRM/*.h`,
`sapi/embed/*.h`, **plus two easy-to-miss one-level-deeper
subdirectories** `main/streams/*.h` (`php_stream_context.h` and
friends) and `Zend/Optimizer/*.h` (`zend_call_graph.h`, `zend_cfg.h`,
etc — pulled in transitively via `zend_compile.h`) — a `find -maxdepth 1`
over just the four top-level directories silently under-copies by ~18
headers, confirmed by diffing a fresh copy's file list against a
previously-vendored one rather than guessing the set is complete. Not
copied into this xcframework preserving that directory structure,
though: an `.xcframework`'s `HeadersPath` only ever contributes ONE
`-I` for its whole `Headers/` folder (unlike a hand-written
`-I<a> -I<b> -I<c>` command line, which a `.binaryTarget` consumer has
no way to replicate) — so all six directories' `*.h` files are copied
flat into one directory instead, `cp`'d by filename only (verified
first: no two headers share a basename across any of them, so this
can't silently shadow one file with another).

Flattening breaks a handful of `#include`s that assumed the original
nested layout — a directory-qualified quote/angle include (`"streams/
php_stream_context.h"`, `<main/php.h>`, `"../TSRM/TSRM.h"`, etc.) has
no matching path once everything sits in one flat directory. These are
patched, in the copied headers only (never touching php-src's own
source), to the bare filename form that resolves correctly once flat:

```sh
# run against BOTH per-target header copies before packaging into the xcframework
sed -i '' 's#include "streams/#include "#' php_streams.h
sed -i '' 's#include <main/php.h>#include "php.h"#' php_embed.h
sed -i '' 's#include <\.\./main/php_config.h>#include "php_config.h"#' zend_config.h
sed -i '' 's#include <\.\./main/config\.w32\.h>#include "config.w32.h"#' zend_config.w32.h
sed -i '' 's#include "\.\./TSRM/TSRM.h"#include "TSRM.h"#' zend_alloc.h zend_portability.h
sed -i '' 's#include "main/php_config.h"#include "php_config.h"#; s#include "zend_config.w32.h"#include "zend_config.w32.h"#' TSRM.h
perl -pi -e 's{(#\s*include\s*)([<"])(?:main|Zend|TSRM|sapi/embed)/([^">]+)([">])}{$1$2$3$4}g' php_embed.h php.h
```

(Re-run `grep -rnE '#[[:space:]]*include[[:space:]]*[<"](main|Zend|TSRM|sapi)/'`
over the flattened copy after patching — it must come back empty
before packaging; a leftover qualified include is a silent build
failure waiting for whoever next has to regenerate this artifact.)

Packaging the two flattened, patched header sets + the two `libphp.a`
slices into the committed xcframework:

```sh
xcodebuild -create-xcframework \
  -library ios-arm64/lib/libphp.a       -headers ios-arm64/include \
  -library ios-arm64-simulator/lib/libphp.a -headers ios-arm64-simulator/include \
  -output libphp.xcframework
```

`libtool`'s own build already links in what PHP's `sapi/embed` needs
beyond libc — `-lresolv -liconv -lm -lsqlite3` at the consumer's link
step is still required (these are system libraries the static archive
doesn't bundle, same as android/README.md's own `libsqlite3.so`
needing to sit alongside `libphp.so`, not inside it).
