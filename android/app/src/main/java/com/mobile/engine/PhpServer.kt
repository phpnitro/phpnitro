package com.mobile.engine

import android.content.Context
import java.io.File
import java.net.InetSocketAddress
import java.net.Socket

/**
 * Runs the bundled static PHP binary (packaged as jniLibs/<abi>/libphp.so so
 * Android lets us exec it from the read-only native library dir) serving the
 * PHP app copied from assets/www to filesDir. The WebView then talks to
 * http://127.0.0.1:PORT — PHP genuinely runs on the device.
 */
class PhpServer(private val context: Context) {

    companion object {
        const val PORT = 8090
    }

    private var process: Process? = null

    fun start() {
        if (isListening()) {
            return
        }

        val www = copyAssets()
        val php = File(context.applicationInfo.nativeLibraryDir, "libphp.so")
        val sessions = File(context.filesDir, "sessions").apply { mkdirs() }

        process = ProcessBuilder(
            php.absolutePath,
            "-S", "127.0.0.1:$PORT",
            "-t", File(www, "public").absolutePath,
            "-d", "session.save_path=${sessions.absolutePath}",
            "-d", "sys_temp_dir=${context.cacheDir.absolutePath}",
            "-d", "error_log=${File(context.filesDir, "php-error.log").absolutePath}",
            File(www, "public/router.php").absolutePath,
        )
            .redirectErrorStream(true)
            .redirectOutput(File(context.filesDir, "php-server.log"))
            .start()

        waitUntilListening()
    }

    private fun copyAssets(): File {
        val target = File(context.filesDir, "www")
        target.deleteRecursively()
        copyAssetDir("www", target)

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

    private fun isListening(): Boolean = try {
        Socket().use { it.connect(InetSocketAddress("127.0.0.1", PORT), 200) }
        true
    } catch (_: Exception) {
        false
    }

    private fun waitUntilListening() {
        repeat(50) {
            if (isListening()) {
                return
            }
            Thread.sleep(100)
        }
    }
}
