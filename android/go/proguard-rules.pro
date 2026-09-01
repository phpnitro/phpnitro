# PhpNitro Go has no JS bridge or reflection-based dispatch of its own
# (that's :app's WebAppInterface, not used here — this app has no WebView
# at all) — CameraX and ML Kit ship their own consumer ProGuard rules
# inside their AARs, so nothing app-specific has been needed here yet. If
# a release build crashes where a debug build doesn't, start by comparing
# behavior with isMinifyEnabled = false before adding rules here.

# Real crash confirmed on a real device, real v0.3 release build (AGP
# 9.0.1): "Unable to get provider androidx.startup.InitializationProvider
# ... Failed to create an instance of class androidx.work.impl.WorkDatabase"
# — WorkManager (pulled in transitively via :engine's
# api("androidx.work:work-runtime-ktx")) only ever instantiates its
# internal Room database (WorkDatabase_Impl) by reflection, so R8 sees no
# direct call site and throws it away; a known AGP 9 R8 regression (its
# default rules used to catch this, they no longer reliably do). Keep the
# generated impl class and every Worker's reflectively-invoked
# constructor explicitly rather than trust the library's own consumer
# rules to still cover it.
-keep class androidx.work.impl.WorkDatabase_Impl { *; }
-keep class * extends androidx.work.ListenableWorker {
    <init>(android.content.Context, androidx.work.WorkerParameters);
}
