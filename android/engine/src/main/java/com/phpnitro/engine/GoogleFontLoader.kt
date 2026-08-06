package com.phpnitro.engine

import android.content.Context
import android.graphics.Typeface
import android.os.Handler
import android.os.Looper
import androidx.core.provider.FontRequest
import androidx.core.provider.FontsContractCompat

/**
 * GoogleFontText's (Engine\Native\GoogleFontText) real font source — the
 * Downloadable Fonts API, which queries the on-device Google Play
 * Services Fonts provider (the same mechanism Android Studio's own
 * "Downloadable fonts" feature and every app using
 * androidx.core.provider.FontRequest already rely on), not a hand-parsed
 * fonts.googleapis.com/css2 response. The provider does its own
 * OS-level, cross-app caching — a font already downloaded by ANY app on
 * the device loads instantly here too. Requires Google Play Services;
 * silently falls through to null (the caller keeps using Roboto) on a
 * device without it, same graceful-degradation shape ImageLoader takes
 * for a failed fetch.
 *
 * Mirrors ImageLoader's own cache/in-flight/callback-on-main-thread
 * shape exactly, just backed by FontsContractCompat instead of a manual
 * HTTP fetch.
 */
object GoogleFontLoader {
    private val cache = mutableMapOf<String, Typeface>()
    private val inFlight = mutableSetOf<String>()
    private val mainHandler = Handler(Looper.getMainLooper())

    fun get(fontFamily: String): Typeface? = cache[fontFamily]

    fun load(context: Context, fontFamily: String, onLoaded: () -> Unit) {
        if (cache.containsKey(fontFamily)) return
        synchronized(inFlight) {
            if (!inFlight.add(fontFamily)) return
        }

        val request = FontRequest(
            "com.google.android.gms.fonts",
            "com.google.android.gms",
            "name=$fontFamily&weight=400&italic=0&besteffort=true",
            R.array.com_google_android_gms_fonts_certs,
        )

        FontsContractCompat.requestFont(
            context.applicationContext,
            request,
            object : FontsContractCompat.FontRequestCallback() {
                override fun onTypefaceRetrieved(typeface: Typeface) {
                    cache[fontFamily] = typeface
                    synchronized(inFlight) { inFlight.remove(fontFamily) }
                    mainHandler.post { onLoaded() }
                }

                override fun onTypefaceRequestFailed(reason: Int) {
                    synchronized(inFlight) { inFlight.remove(fontFamily) }
                }
            },
            mainHandler,
        )
    }
}
