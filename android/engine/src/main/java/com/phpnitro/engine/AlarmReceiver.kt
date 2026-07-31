package com.phpnitro.engine

import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.ActivityCompat
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat

/**
 * android_alarm_manager_plus equivalent — fires even if the app process
 * isn't running (AlarmManager wakes the system, which delivers this
 * broadcast, which starts this receiver in a fresh minimal process if
 * needed). Scheduled by WebAppInterface.scheduleAlarm(); shows a
 * notification directly since there's no running WebView/PHP server to
 * hand the "alarm fired" event to at this point — a background task that
 * needs to run this framework's actual PHP still requires PhpServer to be
 * started, which this receiver does NOT do (out of scope for a v1: see
 * ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md).
 */
class AlarmReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val title = intent.getStringExtra("title") ?: "Rappel"
        val message = intent.getStringExtra("message") ?: ""
        val channelId = "phpx_alarm"

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(channelId, "Alarmes planifiées", NotificationManager.IMPORTANCE_DEFAULT)
            val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.createNotificationChannel(channel)
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ActivityCompat.checkSelfPermission(context, android.Manifest.permission.POST_NOTIFICATIONS)
            != PackageManager.PERMISSION_GRANTED
        ) {
            return
        }

        val notification = NotificationCompat.Builder(context, channelId)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(title)
            .setContentText(message)
            .setAutoCancel(true)
            .build()

        NotificationManagerCompat.from(context).notify(intent.getIntExtra("requestCode", 0), notification)
    }
}
