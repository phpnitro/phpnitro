FROM alpine:3.21 as buildsystem

RUN apk update
RUN apk add wget unzip gcompat libgcc bash patch make curl perl linux-headers pkgconf

WORKDIR /opt
ENV NDK_VERSION android-ndk-r27c-linux
RUN wget https://dl.google.com/android/repository/${NDK_VERSION}.zip && unzip ${NDK_VERSION}.zip && rm ${NDK_VERSION}.zip

ENV PATH="$PATH:/opt/android-ndk-r27c/:/opt/android-ndk-r27c/toolchains/llvm/prebuilt/linux-x86_64/bin"
ENV ANDROID_NDK_ROOT=/opt/android-ndk-r27c
ENV ANDROID_NDK_HOME=/opt/android-ndk-r27c

WORKDIR /root

##########
# CONFIG #
##########
ARG TARGET=armv7a-linux-androideabi32
ARG PHP_VERSION=8.4.2

ENV SQLITE3_VERSION 3470200
RUN wget https://www.sqlite.org/2024/sqlite-amalgamation-${SQLITE3_VERSION}.zip
RUN unzip sqlite-amalgamation-${SQLITE3_VERSION}.zip

WORKDIR /root/sqlite-amalgamation-${SQLITE3_VERSION}
RUN ${TARGET}-clang -o libsqlite3.so -shared -fPIC sqlite3.c

# OpenSSL, cross-compiled static (no-shared) via its own official Android
# Configure targets (android-arm/android-arm64/...) — these already know how
# to derive the right flags from $ANDROID_NDK_ROOT + the NDK clang on PATH,
# see OpenSSL's own NOTES-ANDROID.md. Enables PHP's https:// stream wrapper
# and openssl_* functions, neither of which this binary had before (no TLS
# support at all — every outbound HTTPS call from on-device PHP silently
# failed: Stripe, OAuth providers, Firebase, Feexpay).
WORKDIR /root
ENV OPENSSL_VERSION 3.0.15
RUN wget https://www.openssl.org/source/openssl-${OPENSSL_VERSION}.tar.gz && tar -xzf openssl-${OPENSSL_VERSION}.tar.gz
WORKDIR /root/openssl-${OPENSSL_VERSION}
RUN case "${TARGET}" in \
      armv7a-linux-androideabi*) OSSL_TARGET=android-arm ;; \
      aarch64-linux-android*)    OSSL_TARGET=android-arm64 ;; \
      i686-linux-android*)       OSSL_TARGET=android-x86 ;; \
      x86_64-linux-android*)     OSSL_TARGET=android-x86_64 ;; \
      *) echo "unsupported target for openssl: ${TARGET}" >&2 && exit 1 ;; \
    esac; \
    ./Configure ${OSSL_TARGET} -D__ANDROID_API__=32 no-shared no-tests no-engine \
      --prefix=/root/openssl-install --openssldir=/root/openssl-install --libdir=lib && \
    make -j7 && \
    make install_sw

# Cross-compiling: point pkg-config ONLY at our freshly-built Android
# openssl .pc files (install_sw drops them in $prefix/lib/pkgconfig) —
# never at any host-arch openssl, which would silently produce a PHP
# binary linked against the wrong architecture's libssl/libcrypto.
ENV PKG_CONFIG_PATH=/root/openssl-install/lib/pkgconfig
ENV PKG_CONFIG_LIBDIR=/root/openssl-install/lib/pkgconfig

WORKDIR /root
RUN wget https://www.php.net/distributions/php-${PHP_VERSION}.tar.gz
RUN tar -xvf php-${PHP_VERSION}.tar.gz

COPY *.patch /root/
WORKDIR /root/php-${PHP_VERSION}
RUN \
patch -p1 < ../ext-standard-dns.c.patch && \
patch -p1 < ../resolv.patch && \
patch -p1 < ../ext-standard-php_fopen_wrapper.c.patch && \
patch -p1 < ../main-streams-cast.c.patch && \
patch -p1 < ../fork.patch \
;

WORKDIR /root
RUN mkdir build install
WORKDIR /root/build

RUN ../php-${PHP_VERSION}/configure \
  --host=${TARGET} \
  --disable-dom \
  --disable-simplexml \
  --disable-xml \
  --disable-xmlreader \
  --disable-xmlwriter \
  --without-pear \
  --without-libxml \
  SQLITE_CFLAGS="-I/root/sqlite-amalgamation-${SQLITE3_VERSION}" \
  SQLITE_LIBS="-lsqlite3 -L/root/sqlite-amalgamation-${SQLITE3_VERSION}" \
  CC=$TARGET-clang \
  --disable-phar \
  --disable-phpdbg \
  --with-sqlite3 \
  --with-pdo-sqlite \
  --with-openssl=/root/openssl-install \
  ;

RUN \
  for hdr in resolv_params.h resolv_private.h resolv_static.h resolv_stats.h; do \
    curl https://android.googlesource.com/platform/bionic/+/refs/heads/android12--mainline-release/libc/dns/include/$hdr?format=TEXT | base64 -d > $hdr; \
  done
RUN make -j7 sapi/cli/php
RUN cp /root/build/sapi/cli/php /root/install/php.so
RUN cp /root/sqlite-amalgamation-${SQLITE3_VERSION}/libsqlite3.so /root/install/libsqlite3.so

FROM scratch
ARG LIBDIR
ENV LIBDIR ${LIBDIR}
COPY --from=buildsystem /root/install/* /app/src/main/jniLibs/${LIBDIR}/
