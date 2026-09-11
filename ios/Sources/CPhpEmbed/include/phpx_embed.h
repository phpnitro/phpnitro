/*
 * A thin C shim over PHP's embed SAPI (sapi/embed/php_embed.h),
 * exposing the three operations PhpEmbedBridge.swift actually needs
 * as plain C functions Swift can call directly — php_embed_init()/
 * php_embed_shutdown() themselves are already extern "C" and callable
 * from Swift as-is, but PHP_EMBED_START_BLOCK/END_BLOCK are C macros
 * (zend_first_try/zend_catch expand to setjmp-based exception
 * handling), which Swift cannot call at all. zend_eval_string() also
 * writes its output through the SAPI's own ub_write callback — meant
 * for a real request/response cycle with a socket or stdout on the
 * other end, not for getting a script's output back as a value — so
 * phpx_embed_eval() installs its own ub_write that appends into an
 * in-memory buffer instead, returning it as a plain C string.
 *
 * Single-threaded, single embed-SAPI-instance use only, same as this
 * repo's own NativeScreenViewController (there is exactly one PHP
 * "server" — embedded here, or `phpx serve` over the network — for the
 * lifetime of this app's process).
 */
#ifndef PHPX_EMBED_H
#define PHPX_EMBED_H

#ifdef __cplusplus
extern "C" {
#endif

/* Starts the embed SAPI. Call exactly once before any phpx_embed_eval(). */
void phpx_embed_start(void);

/*
 * Evaluates php_code and returns everything it wrote via echo/print as
 * a newly heap-allocated, NUL-terminated string — the caller owns it
 * and must release it with phpx_embed_free_string(). Returns NULL if
 * phpx_embed_start() hasn't run yet, or if compiling/executing
 * php_code raised an uncaught exception/parse error (same "no partial
 * output on failure" contract a real HTTP response would give you).
 */
char *phpx_embed_eval(const char *php_code);

/*
 * Like phpx_embed_eval(), but first closes whatever request is
 * currently open (php_request_shutdown()) and opens a brand new one
 * (php_request_startup()) — the same per-request state reset a real
 * PHP SAPI (mod_php, FPM) gives every incoming HTTP request for free.
 * Without this, a second phpx_embed_eval() that `require`s the same
 * script a first one already required (e.g. this app's own
 * public/index.php, needed once per "screen fetch") fails with
 * "Cannot redeclare class/function" — PHP's engine only tracks
 * declared symbols and included files per request, not per process.
 * phpx_embed_start() itself already opens the FIRST request (as part
 * of what php_embed_init() does internally) — this is for every
 * request after that one.
 */
char *phpx_embed_handle_request(const char *php_code);

void phpx_embed_free_string(char *str);

/* Shuts the embed SAPI down. Safe to call even if never started. */
void phpx_embed_shutdown(void);

#ifdef __cplusplus
}
#endif

#endif
