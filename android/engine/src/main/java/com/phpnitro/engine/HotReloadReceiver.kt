package com.phpnitro.engine

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

/**
 * `phpx dev:push`'s trigger — after pushing edited PHP straight into the
 * running app's `filesDir/www` (bypassing the APK entirely: no
 * assemble/install, see PhpServer.kt's copyAssets() for why that directory
 * is writable and where PhpServer -S actually serves from), this broadcast
 * tells the live Activity to refetch its current screen. `php -S` re-reads
 * and recompiles each .php file straight off disk with no persistent
 * opcache, so the pushed edit is already live the instant this refetch
 * lands — no Activity restart, no lost screenStack/session, no APK
 * rebuild. Only wakes a resumed Activity (NativeRenderPocActivity.
 * hotReloadInstance), matching the "app must actually be open for this to
 * mean anything" nature of a dev-only affordance. Non-exported like
 * AlarmReceiver/GeofenceReceiver, but `adb shell am broadcast -n` can still
 * target it explicitly on a debuggable build — that's the whole point.
 */
class HotReloadReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        NativeRenderPocActivity.hotReloadInstance?.get()?.let { activity ->
            activity.runOnUiThread { activity.hotReload() }
        }
    }
}
