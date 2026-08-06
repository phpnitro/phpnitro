package com.phpnitro.engine

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.Service
import android.content.Intent
import android.os.Build
import android.os.Handler
import android.os.IBinder
import android.os.Looper
import androidx.core.app.NotificationCompat
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import okhttp3.WebSocket
import okhttp3.WebSocketListener

/**
 * Engine\Device\WebSocket's real connection — deliberately a foreground
 * Service, not a field on NativeRenderPocActivity, precisely so the
 * connection survives the app being backgrounded (an explicit product
 * choice, not the default "close it, it's simpler" every other
 * short-lived capability here takes). Android has required a persistent
 * notification for any long-running background work since API 26, and a
 * declared foreground-service TYPE since API 34 ("dataSync" — a network
 * connection, not media/location/etc., see AndroidManifest.xml) — both
 * are non-negotiable OS requirements this class has no way to opt out
 * of, not a design choice made here.
 *
 * Single connection at a time — same "one active slot" scope
 * NativeRenderPocActivity's own pendingMicToken/pendingQrOutputField
 * already take for their own single-flight state. A second
 * device:wsconnect while one is already open replaces it.
 *
 * Started (survives Activity destruction/recreation) AND bound (lets a
 * live Activity talk to it directly and get pushed new messages without
 * polling) — see NativeRenderPocActivity's ensureWebSocketServiceBound().
 * unbindService() alone does NOT stop a service that was also started;
 * only an explicit "device:wsdisconnect" (which calls stopService())
 * actually shuts this down and removes the notification.
 */
class WebSocketService : Service() {
    private val client = OkHttpClient()
    private val mainHandler = Handler(Looper.getMainLooper())
    private var socket: WebSocket? = null
    private var outputField: String = "ws_out"

    @Volatile
    var lastMessage: String? = null
        private set

    private var listener: ((String) -> Unit)? = null

    inner class LocalBinder : android.os.Binder() {
        fun service(): WebSocketService = this@WebSocketService
    }

    private val binder = LocalBinder()

    override fun onBind(intent: Intent?): IBinder = binder

    override fun onCreate() {
        super.onCreate()
        startForeground(NOTIFICATION_ID, buildNotification())
    }

    fun currentOutputField(): String = outputField

    /**
     * Fired on every NEW message while a client is bound — never replays
     * lastMessage on registration, the caller reads lastMessage/
     * currentOutputField() itself to sync state on (re)bind (see
     * NativeRenderPocActivity's onServiceConnected()).
     */
    fun setListener(callback: ((String) -> Unit)?) {
        listener = callback
    }

    fun connect(url: String, outputField: String) {
        socket?.close(1000, "reconnecting")
        this.outputField = outputField
        lastMessage = null

        val request = Request.Builder().url(url).build()
        socket = client.newWebSocket(
            request,
            object : WebSocketListener() {
                override fun onMessage(webSocket: WebSocket, text: String) {
                    lastMessage = text
                    mainHandler.post { listener?.invoke(text) }
                }

                override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
                    val message = "Erreur : ${t.message}"
                    lastMessage = message
                    mainHandler.post { listener?.invoke(message) }
                }

                override fun onClosed(webSocket: WebSocket, code: Int, reason: String) {
                    if (socket === webSocket) socket = null
                }
            },
        )
    }

    fun send(message: String) {
        socket?.send(message)
    }

    fun disconnect() {
        socket?.close(1000, "client disconnect")
        socket = null
    }

    private fun buildNotification(): Notification {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val manager = getSystemService(NotificationManager::class.java)
            if (manager.getNotificationChannel(CHANNEL_ID) == null) {
                manager.createNotificationChannel(
                    NotificationChannel(CHANNEL_ID, "Connexion temps réel", NotificationManager.IMPORTANCE_MIN),
                )
            }
        }

        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("PhpNitro")
            .setContentText("Connexion temps réel active")
            .setSmallIcon(android.R.drawable.stat_notify_sync)
            .setPriority(NotificationCompat.PRIORITY_MIN)
            .setOngoing(true)
            .build()
    }

    override fun onDestroy() {
        socket?.close(1000, "service destroyed")
        super.onDestroy()
    }

    companion object {
        private const val NOTIFICATION_ID = 4821
        private const val CHANNEL_ID = "phpnitro_websocket"
    }
}
