package com.mobile.engine

import android.content.Context
import java.io.File
import java.net.InetSocketAddress
import java.net.Socket

/**
 * Runs the bundled PHP binary — a normal cross-compiled PHP CLI executable
 * packaged as jniLibs/<abi>/libphp.so purely so Android's installer places
 * it in the one app-owned directory that is executable but not
 * app-writable (W^X forbids exec from anywhere the app itself can write).
 * It is dynamically linked against libsqlite3.so, bundled the same way, so
 * LD_LIBRARY_PATH must point at the native library dir too.
 *
 * Serves the PHP app copied from assets/www to filesDir. The WebView then
 * talks to http://127.0.0.1:PORT — PHP genuinely runs on the device.
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
        val nativeDir = context.applicationInfo.nativeLibraryDir
        val php = File(nativeDir, "libphp.so")
        val sessions = File(context.filesDir, "sessions").apply { mkdirs() }
        val tmp = context.cacheDir

        val builder = ProcessBuilder(
            php.absolutePath,
            "-S", "127.0.0.1:$PORT",
            "-t", File(www, "public").absolutePath,
            "-d", "session.save_path=${sessions.absolutePath}",
            "-d", "sys_temp_dir=${tmp.absolutePath}",
            "-d", "error_log=${File(context.filesDir, "php-error.log").absolutePath}",
            File(www, "public/router.php").absolutePath,
        )
            .redirectErrorStream(true)
            .redirectOutput(File(context.filesDir, "php-server.log"))

        builder.environment()["LD_LIBRARY_PATH"] = nativeDir
        builder.environment()["TMPDIR"] = tmp.absolutePath

        process = builder.start()

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
        repeat(80) {
            if (isListening()) {
                return
            }
            Thread.sleep(150)
        }
    }
}
