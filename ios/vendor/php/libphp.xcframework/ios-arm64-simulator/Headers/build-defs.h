/*
   +----------------------------------------------------------------------+
   | Copyright (c) The PHP Group                                          |
   +----------------------------------------------------------------------+
   | This source file is subject to version 3.01 of the PHP license,      |
   | that is bundled with this package in the file LICENSE, and is        |
   | available through the world-wide-web at the following url:           |
   | https://www.php.net/license/3_01.txt                                 |
   | If you did not receive a copy of the PHP license and are unable to   |
   | obtain it through the world-wide-web, please send a note to          |
   | license@php.net so we can mail you a copy immediately.               |
   +----------------------------------------------------------------------+
   | Author: Stig Sæther Bakken <ssb@php.net>                             |
   +----------------------------------------------------------------------+
*/

#define CONFIGURE_COMMAND " './configure'  '--host=aarch64-apple-darwin' '--disable-all' '--without-pear' '--disable-cli' '--disable-cgi' '--disable-phpdbg' '--enable-embed=static' '--without-pcre-jit' '--enable-session=static' '--enable-pdo=static' '--with-pdo-sqlite=static' '--with-sqlite3=static' 'host_alias=aarch64-apple-darwin' 'SQLITE_CFLAGS=-I/Applications/Xcode.app/Contents/Developer/Platforms/iPhoneSimulator.platform/Developer/SDKs/iPhoneSimulator26.5.sdk/usr/include' 'SQLITE_LIBS=-L/Applications/Xcode.app/Contents/Developer/Platforms/iPhoneSimulator.platform/Developer/SDKs/iPhoneSimulator26.5.sdk/usr/lib -lsqlite3'"
#define PHP_ODBC_CFLAGS	""
#define PHP_ODBC_LFLAGS		""
#define PHP_ODBC_LIBS		""
#define PHP_ODBC_TYPE		""
#define PHP_PROG_SENDMAIL	"/usr/sbin/sendmail"
#define PEAR_INSTALLDIR         ""
#define PHP_INCLUDE_PATH	".:"
#define PHP_EXTENSION_DIR       "/usr/local/lib/php/extensions/no-debug-non-zts-20240924"
#define PHP_PREFIX              "/usr/local"
#define PHP_BINDIR              "/usr/local/bin"
#define PHP_SBINDIR             "/usr/local/sbin"
#define PHP_MANDIR              "/usr/local/php/man"
#define PHP_LIBDIR              "/usr/local/lib/php"
#define PHP_DATADIR             "/usr/local/share/php"
#define PHP_SYSCONFDIR          "/usr/local/etc"
#define PHP_LOCALSTATEDIR       "/usr/local/var"
#define PHP_CONFIG_FILE_PATH    "/usr/local/lib"
#define PHP_CONFIG_FILE_SCAN_DIR    ""
#define PHP_SHLIB_SUFFIX        "so"
#define PHP_SHLIB_EXT_PREFIX    ""
