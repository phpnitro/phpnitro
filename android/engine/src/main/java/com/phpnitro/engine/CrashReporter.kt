package com.phpnitro.engine

import android.content.Context
import android.util.Log
import org.json.JSONObject
import java.io.File
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * Local, dependency-free crash reporting — no Sentry/Crashlytics account
 * needed for something better than "the app silently vanished" when a
 * real user's install crashes. install() replaces the default uncaught-
 * exception handler with one that persists a structured JSON record to
 * disk (survives the crash that's about to kill the process) BEFORE
 * delegating to whatever handler was already registered — normally the
 * OS's own, so the standard "app has stopped" dialog and any other
 * existing behavior are untouched, this only adds a side effect. Runs in
 * every build, not just debug: production crashes are the ones that
 * actually need this, debug ones are usually caught in Android Studio's
 * own debugger first.
 *
 * logPhpError() captures this engine's OTHER real failure mode — a PHP
 * exception surfaced through the JSON error overlay (see
 * NativeRenderPocActivity.showScreenErrorOverlay()) — into the exact same
 * log, so "everything that went wrong" lives in one place instead of two
 * unrelated logging paths a developer would have to know to check
 * separately.
 *
 * No network call anywhere in this file — reports stay on-device until a
 * user (or developer, over adb) actually shares them (see
 * "device:report_crash" in NativeRenderPocActivity's handleDeviceAction(),
 * NativeSettingsScreen's "Signaler un problème"). That's a real, if
 * manual, path to a developer's inbox — not a background upload to a
 * third-party service this framework has no account for.
 */
object CrashReporter {
    private const val MAX_STORED_REPORTS = 50
    private const val LOG_FILE_NAME = "crash_reports.jsonl"

    fun install(context: Context) {
        val appContext = context.applicationContext
        val previousHandler = Thread.getDefaultUncaughtExceptionHandler()
        Thread.setDefaultUncaughtExceptionHandler { thread, throwable ->
            try {
                persist(
                    appContext,
                    JSONObject().apply {
                        put("type", "native")
                        put("timestamp", isoNow())
                        put("thread", thread.name)
                        put("message", throwable.message ?: throwable.javaClass.name)
                        put("stackTrace", Log.getStackTraceString(throwable))
                    },
                )
            } catch (loggingFailure: Exception) {
                // Never let the crash reporter itself be the reason the
                // real crash handler doesn't run at all.
                Log.e("CrashReporter", "failed to persist crash report", loggingFailure)
            }
            previousHandler?.uncaughtException(thread, throwable)
        }
    }

    fun logPhpError(context: Context, errorJson: JSONObject) {
        persist(
            context.applicationContext,
            JSONObject().apply {
                put("type", "php")
                put("timestamp", isoNow())
                put("class", errorJson.optString("class", "?"))
                put("message", errorJson.optString("message", "?"))
                put("file", errorJson.optString("file", ""))
                put("line", errorJson.optInt("line", -1))
                put("stackTrace", errorJson.optString("trace", ""))
            },
        )
    }

    /** Most recent first — see NativeSettingsScreen's "Signaler un problème" for the real consumer. */
    fun recentReports(context: Context): List<JSONObject> {
        val file = logFile(context.applicationContext)
        if (!file.exists()) return emptyList()
        return file.readLines()
            .mapNotNull { line -> runCatching { JSONObject(line) }.getOrNull() }
            .asReversed()
    }

    /** Plain text, ready for Intent.ACTION_SEND (see NativeDeviceBridge.share()) — not a file attachment, no FileProvider needed. */
    fun formatForSharing(context: Context): String {
        val reports = recentReports(context)
        if (reports.isEmpty()) return "Aucun rapport de plantage enregistré."

        return reports.joinToString("\n\n---\n\n") { report ->
            buildString {
                append("[${report.optString("timestamp")}] ${report.optString("type")}\n")
                if (report.has("class")) append("${report.optString("class")}: ")
                append(report.optString("message"))
                val file = report.optString("file", "")
                if (file.isNotEmpty()) append("\n${file}:${report.optInt("line", -1)}")
                val trace = report.optString("stackTrace", "")
                if (trace.isNotEmpty()) append("\n$trace")
            }
        }
    }

    fun logFile(context: Context): File = File(context.applicationContext.filesDir, LOG_FILE_NAME)

    fun clear(context: Context) {
        logFile(context.applicationContext).delete()
    }

    private fun persist(context: Context, record: JSONObject) {
        val file = logFile(context)
        val existing = if (file.exists()) file.readLines() else emptyList()
        val updated = (existing + record.toString()).takeLast(MAX_STORED_REPORTS)
        file.writeText(updated.joinToString("\n") + "\n")
    }

    private fun isoNow(): String = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'", Locale.US).format(Date())
}
