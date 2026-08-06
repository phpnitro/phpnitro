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
import com.google.android.gms.location.Geofence
import com.google.android.gms.location.GeofencingEvent

/**
 * android_alarm_manager_plus-style geofencing (Engine\Device\Geofence) —
 * real enter/exit zone transitions via Play Services' GeofencingClient, not
 * a periodic location poll. Same "no running WebView to hand this to"
 * situation as AlarmReceiver: shows a notification directly rather than
 * routing through PHP, since the process may not even be alive when a
 * transition fires.
 */
class GeofenceReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val event = GeofencingEvent.fromIntent(intent) ?: return
        if (event.hasError()) return

        val transition = when (event.geofenceTransition) {
            Geofence.GEOFENCE_TRANSITION_ENTER -> "entrée dans"
            Geofence.GEOFENCE_TRANSITION_EXIT -> "sortie de"
            else -> return
        }
        val ids = event.triggeringGeofences?.joinToString(", ") { it.requestId } ?: return

        val channelId = "phpx_geofence"
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(channelId, "Zones géographiques", NotificationManager.IMPORTANCE_DEFAULT)
            (context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager)
                .createNotificationChannel(channel)
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ActivityCompat.checkSelfPermission(context, android.Manifest.permission.POST_NOTIFICATIONS)
            != PackageManager.PERMISSION_GRANTED
        ) {
            return
        }

        val notification = NotificationCompat.Builder(context, channelId)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle("Zone géographique")
            .setContentText("$transition : $ids")
            .setAutoCancel(true)
            .build()

        NotificationManagerCompat.from(context).notify(ids.hashCode(), notification)
    }
}
