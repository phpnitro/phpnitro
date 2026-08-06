package com.phpnitro.engine

import android.content.Context
import java.net.CookieStore
import java.net.HttpCookie
import java.net.URI

/**
 * A CookieStore backed by SharedPreferences instead of CookieManager's
 * default in-memory one — without this, PHPSESSID never survives Android
 * killing the whole app process while backgrounded (routine under memory
 * pressure, not just a swipe-away): the embedded PhpServer's session
 * files under filesDir/sessions/ are already disk-backed and would
 * happily resume a session on the very next request, but a fresh process
 * means a fresh CookieManager with nothing in it, so the client presents
 * no PHPSESSID at all and the server hands out a brand new one — every
 * $_SESSION value (auth_user, the stepper's progress, the reorder/
 * dismiss demo's item lists) resets to empty even though the file that
 * held it never actually went anywhere.
 *
 * Deliberately not a general-purpose CookieStore: no per-domain/per-path
 * scoping, no expiry handling. NativeRenderPocActivity only ever talks to
 * one origin (http://127.0.0.1:<port>, a port that can change between
 * launches) and needs exactly one cookie (PHPSESSID) to survive — the
 * simplification a real multi-origin browser cookie jar couldn't make is
 * fine here.
 */
class PersistentCookieStore(context: Context) : CookieStore {
    private val prefs = context.getSharedPreferences("phpx_cookies", Context.MODE_PRIVATE)
    private val cookies = mutableMapOf<String, HttpCookie>()

    init {
        prefs.getString("cookies", null)?.split("\n")?.forEach { line ->
            val parts = line.split("=", limit = 2)
            if (parts.size == 2 && parts[0].isNotBlank()) {
                cookies[parts[0]] = HttpCookie(parts[0], parts[1])
            }
        }
    }

    private fun persist() {
        val serialized = cookies.values.joinToString("\n") { "${it.name}=${it.value}" }
        prefs.edit().putString("cookies", serialized).apply()
    }

    @Synchronized
    override fun add(uri: URI?, cookie: HttpCookie?) {
        if (cookie == null) return
        cookies[cookie.name] = cookie
        persist()
    }

    @Synchronized
    override fun get(uri: URI?): MutableList<HttpCookie> = cookies.values.toMutableList()

    @Synchronized
    override fun getCookies(): MutableList<HttpCookie> = cookies.values.toMutableList()

    override fun getURIs(): MutableList<URI> = mutableListOf()

    @Synchronized
    override fun remove(uri: URI?, cookie: HttpCookie?): Boolean {
        val removed = cookie != null && cookies.remove(cookie.name) != null
        if (removed) persist()
        return removed
    }

    @Synchronized
    override fun removeAll(): Boolean {
        val had = cookies.isNotEmpty()
        cookies.clear()
        persist()
        return had
    }
}
