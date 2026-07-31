package com.phpnitro.engine

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.os.Handler
import android.os.Looper
import android.util.Base64
import android.util.LruCache
import java.net.HttpURLConnection
import java.net.URL
import kotlin.concurrent.thread

/**
 * Minimal network image loader for RenderImage.php's "image" draw
 * command — no Glide/Coil dependency added just for this. An in-memory
 * LRU cache (~24 decoded bitmaps, not bytes — this view never shows more
 * than a handful of images at once) keyed by URL, one background thread
 * per distinct in-flight URL (deduped so a fast re-render — the phase 5
 * crossfade re-requesting the same frame — doesn't refetch), completion
 * posted back to the main thread.
 */
object ImageLoader {
    private val cache = LruCache<String, Bitmap>(24)
    private val inFlight = mutableSetOf<String>()
    private val mainHandler = Handler(Looper.getMainLooper())

    fun get(url: String): Bitmap? = cache.get(url)

    fun load(url: String, onLoaded: () -> Unit) {
        if (cache.get(url) != null) return
        synchronized(inFlight) {
            if (!inFlight.add(url)) return
        }

        thread {
            try {
                // A camera-captured or gallery-picked image (see
                // NativeDeviceBridge.kt's capturePhoto()/pickImage()) comes
                // back as a base64 data: URI, not a real network location —
                // decode it directly instead of a doomed HTTP fetch.
                val bitmap = if (url.startsWith("data:")) {
                    val base64Payload = url.substringAfter(",", "")
                    val bytes = Base64.decode(base64Payload, Base64.DEFAULT)
                    BitmapFactory.decodeByteArray(bytes, 0, bytes.size)
                } else {
                    val connection = URL(url).openConnection() as HttpURLConnection
                    connection.connectTimeout = 8000
                    connection.readTimeout = 8000
                    val decoded = connection.inputStream.use { BitmapFactory.decodeStream(it) }
                    connection.disconnect()
                    decoded
                }
                if (bitmap != null) {
                    cache.put(url, bitmap)
                    mainHandler.post { onLoaded() }
                }
            } catch (e: Exception) {
                android.util.Log.e("ImageLoader", "Failed to load $url", e)
            } finally {
                synchronized(inFlight) { inFlight.remove(url) }
            }
        }
    }
}
