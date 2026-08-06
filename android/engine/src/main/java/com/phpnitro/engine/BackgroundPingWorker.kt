package com.phpnitro.engine

import android.content.Context
import androidx.work.Worker
import androidx.work.WorkerParameters
import java.net.HttpURLConnection
import java.net.URL

/**
 * android_alarm_manager_plus/WorkManager equivalent — fires periodically
 * (WorkManager's own floor: every 15 minutes minimum) even when the app
 * isn't in the foreground, POSTing to whatever endpoint
 * Engine\Device\BackgroundTask::schedule() configured. Deliberately dumb
 * (no PHP/WebView involved here): starting the embedded PHP server from a
 * background worker process is out of scope for this first version.
 */
class BackgroundPingWorker(context: Context, params: WorkerParameters) : Worker(context, params) {
    override fun doWork(): Result {
        val endpoint = inputData.getString("endpoint") ?: return Result.failure()

        return try {
            val connection = URL(endpoint).openConnection() as HttpURLConnection
            connection.requestMethod = "POST"
            connection.connectTimeout = 10_000
            connection.readTimeout = 10_000
            connection.responseCode
            connection.disconnect()
            Result.success()
        } catch (_: Exception) {
            Result.retry()
        }
    }
}
