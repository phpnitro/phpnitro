# WebAppInterface's bridge methods are called by name from JS
# (window.AndroidNative.vibrate(...), etc.) via WebView's reflection-based
# @JavascriptInterface dispatch — R8 has no way to see that a JS string
# calls a Kotlin method, so without this rule minification/obfuscation
# would rename or strip them and silently break every native capability
# (vibrate, camera, biometrics, share, deep linking helpers, sensors...)
# in a real release build. This is the single most important rule in this
# file; everything else is comparatively low-risk.
-keepclassmembers class com.mobile.engine.WebAppInterface {
    @android.webkit.JavascriptInterface <methods>;
}
-keep public class com.mobile.engine.WebAppInterface

# WorkManager resolves a Worker by the class name string it recorded at
# schedule time — a target that R8 tree-shakes away (only ever referenced
# via a generic type parameter, not a direct `new`) would then fail to
# instantiate at the exact moment the periodic background ping fires.
-keep class com.mobile.engine.BackgroundPingWorker { *; }

# A DIFFERENT WorkManager failure than the one above, confirmed on a real
# device on :go's real v0.3 release build (AGP 9.0.1): "Unable to get
# provider androidx.startup.InitializationProvider ... Failed to create an
# instance of class androidx.work.impl.WorkDatabase" — WorkManager's own
# internal Room database (WorkDatabase_Impl) is only ever instantiated by
# reflection, so R8 sees no direct call site and throws it away; a known
# AGP 9 R8 regression (its default/consumer rules used to catch this, they
# no longer reliably do). :app pulls in the same androidx.work dependency
# transitively via :engine and has never been through a real signed
# release build (see README's own "pas encore vérifié par un vrai build
# signé" note) — applying the same fix here proactively rather than
# waiting to hit it independently.
-keep class androidx.work.impl.WorkDatabase_Impl { *; }
-keep class * extends androidx.work.ListenableWorker {
    <init>(android.content.Context, androidx.work.WorkerParameters);
}

# AndroidManifest.xml's <receiver android:name=".AlarmReceiver"> resolves
# the class by that same literal name at broadcast-delivery time. AGP's own
# default consumer rules already keep manifest-declared components in the
# common case, but this is cheap insurance against a subtly different
# behavior in a future AGP version silently reintroducing this exact class
# of bug (an unminified debug build would never have caught it either).
-keep class com.mobile.engine.AlarmReceiver { *; }

# androidx.security:security-crypto (SecureStorage.php's native backing)
# pulls in Google Tink, which references these compile-time-only annotation
# packages (errorprone, javax.annotation) without shipping them as a runtime
# dependency — R8 fails the whole release build on the missing classes
# unless told they're safe to not find, exactly as Tink's own upstream
# consumer-proguard-rules recommend. Found by actually running
# assembleRelease, not anticipated in advance.
-dontwarn com.google.errorprone.annotations.**
-dontwarn javax.annotation.**
