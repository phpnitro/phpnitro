# PhpNitro Go has no JS bridge or reflection-based dispatch of its own
# (that's :app's WebAppInterface, not used here — this app has no WebView
# at all). CameraX's own consumer ProGuard rules have been enough so far;
# ML Kit's have NOT (see below) — if a release build crashes where a
# debug build doesn't, start by comparing behavior with
# isMinifyEnabled = false before adding rules here.

# Real crash confirmed on a real device (ScanActivity, camera preview up,
# first scan attempt), real release build (AGP 9.0.1): NullPointerException
# invoking a method on a null com.google.mlkit.vision.barcode.internal.zzh
# — ML Kit barcode-scanning resolves its internal scanner client through
# its own dynamic-loading indirection (obfuscated zz*-named classes,
# consistent with every Play-Services-family SDK), which R8 partially
# strips/renames despite the AAR's bundled consumer rules; a documented
# ML Kit + R8 issue (googlesamples/mlkit#213 hits the exact same null
# BarcodeScannerImpl$zza pattern).
#
# First attempt kept only com.google.mlkit.vision.barcode.** — made it
# WORSE (crashed at launch instead of at scan): ML Kit's internal
# dependency-injection graph (its own MlKitInitProvider, same
# androidx.startup-ContentProvider idiom WorkManager uses above) wires
# barcode's internal Component against com.google.mlkit.common.sdkinternal
# classes — a DIFFERENT package R8 was still free to rename. This isn't
# "the two classes that happened to crash so far" — com.google.mlkit.common
# is the shared internal machinery EVERY ML Kit feature module depends on
# to bootstrap, so scope the keep to the whole SDK rather than keep
# rediscovering which sub-package the dependency graph reaches into next.
-keep class com.google.mlkit.** { *; }
-dontwarn com.google.mlkit.**

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
