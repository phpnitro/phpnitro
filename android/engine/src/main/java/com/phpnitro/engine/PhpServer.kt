package com.phpnitro.engine

import android.content.Context
import java.io.File
import java.net.InetSocketAddress
import java.net.ServerSocket
import java.net.Socket
import java.security.SecureRandom

/**
 * Runs the bundled PHP binary — a normal cross-compiled PHP CLI executable
 * packaged as jniLibs/<abi>/libphp.so purely so Android's installer places
 * it in the one app-owned directory that is executable but not
 * app-writable (W^X forbids exec from anywhere the app itself can write).
 * It is dynamically linked against libsqlite3.so, bundled the same way, so
 * LD_LIBRARY_PATH must point at the native library dir too.
 *
 * Serves the PHP app copied from assets/www to filesDir. The WebView then
 * talks to http://127.0.0.1:<port> — PHP genuinely runs on the device.
 *
 * The port is picked dynamically (an OS-assigned free ephemeral port)
 * instead of a hardcoded one: multiple apps built with this framework can
 * end up on the same device (e.g. a demo app + a real app, both using
 * PhpServer), and a shared hardcoded port would collide if both happen to
 * be running at once — one app's WebView could end up talking to a
 * different app's PHP process entirely. A dynamic port makes that
 * structurally impossible rather than relying on cleanup/ordering.
 */
class PhpServer(private val context: Context) {

    companion object {
        private const val PREFS_NAME = "phpx_server"
        private const val PREF_PORT = "port"

        /**
         * Last known bound port, persisted so other components started
         * independently of MainActivity (e.g. a push notification service
         * reacting to a message) can still reach the running server without
         * needing a hardcoded port.
         */
        fun lastKnownPort(context: Context): Int =
            context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE).getInt(PREF_PORT, 0)
    }

    private var process: Process? = null
    private var port: Int = 0

    /**
     * A fresh random secret each launch, known only to this process and
     * the PHP child it spawns (via env var, never logged, never written
     * to a file another app could read) — see /native/layout-demo's own
     * check in public/index.php for why: PHP binds 127.0.0.1 only, so no
     * OTHER device can ever reach it, but any OTHER app installed on this
     * SAME device (Android doesn't restrict loopback between apps) could
     * still port-scan 127.0.0.1, find it, and drive login/register/
     * payments/etc. through it with zero prior knowledge required —
     * every action for every screen lives behind that one route (see
     * NativeRenderPocActivity's own fetchDrawCommands(), its only
     * caller). This token is that route's whole defense against that:
     * a co-located app can find the PORT by scanning, but can't guess
     * this. Deliberately NOT applied to phpx serve (bin/phpx's own dev
     * server never sets this env var) — that one's threat model is
     * already documented and accepted (LAN-reachable by design, for
     * PhpNitro Go, same trade-off `expo start` makes).
     */
    val accessToken: String = ByteArray(24).also { SecureRandom().nextBytes(it) }
        .joinToString("") { "%02x".format(it) }

    /** Returns the port the server actually bound to. */
    fun start(): Int {
        val freePort = findFreePort()
        port = freePort
        context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE).edit()
            .putInt(PREF_PORT, freePort)
            .apply()

        val www = copyAssets()
        val cacert = copyCacert()
        val nativeDir = context.applicationInfo.nativeLibraryDir
        val php = File(nativeDir, "libphp.so")
        val sessions = File(context.filesDir, "sessions").apply { mkdirs() }
        val tmp = context.cacheDir

        val builder = ProcessBuilder(
            php.absolutePath,
            "-S", "127.0.0.1:$freePort",
            "-t", File(www, "public").absolutePath,
            "-d", "session.save_path=${sessions.absolutePath}",
            "-d", "sys_temp_dir=${tmp.absolutePath}",
            "-d", "error_log=${File(context.filesDir, "php-error.log").absolutePath}",
            "-d", "openssl.cafile=${cacert.absolutePath}",
            File(www, "public/router.php").absolutePath,
        )
            .redirectErrorStream(true)
            .redirectOutput(File(context.filesDir, "php-server.log"))

        builder.environment()["LD_LIBRARY_PATH"] = nativeDir
        builder.environment()["TMPDIR"] = tmp.absolutePath
        builder.environment()["PHPNITRO_ACCESS_TOKEN"] = accessToken

        process = builder.start()

        waitUntilListening(freePort)

        return freePort
    }

    /** Kills the PHP subprocess. Call from onDestroy for a clean shutdown. */
    fun stop() {
        process?.destroy()
        process = null
    }

    private fun findFreePort(): Int = ServerSocket(0).use { it.localPort }

    private fun copyAssets(): File {
        val target = File(context.filesDir, "www")
        target.deleteRecursively()
        copyAssetDir("www", target)

        return target
    }

    /**
     * The cross-compiled PHP binary has no system CA store to fall back on
     * (Android keeps its trust store in a format OpenSSL can't read
     * directly, unlike a normal Linux distro's /etc/ssl/certs) — without
     * this, every outbound https:// call fails with "certificate verify
     * failed" even though TLS itself negotiates fine. Mozilla's bundle
     * (assets/cacert.pem, same one curl/most distros ship) is copied out of
     * the read-only APK assets once per launch and pointed to via
     * openssl.cafile above.
     */
    private fun copyCacert(): File {
        val target = File(context.filesDir, "cacert.pem")
        context.assets.open("cacert.pem").use { input ->
            target.outputStream().use { output -> input.copyTo(output) }
        }

        return target
    }

    private fun copyAssetDir(assetPath: String, target: File) {
        val entries = context.assets.list(assetPath) ?: return

        if (entries.isEmpty()) {
            target.parentFile?.mkdirs()
            context.assets.open(assetPath).use { input ->
                target.outputStream().use { output -> input.copyTo(output) }
            }
            return
        }

        target.mkdirs()
        for (entry in entries) {
            copyAssetDir("$assetPath/$entry", File(target, entry))
        }
    }

    private fun isListening(port: Int): Boolean = try {
        Socket().use { it.connect(InetSocketAddress("127.0.0.1", port), 200) }
        true
    } catch (_: Exception) {
        false
    }

    private fun waitUntilListening(port: Int) {
        repeat(80) {
            if (isListening(port)) {
                return
            }
            Thread.sleep(150)
        }
    }
}
