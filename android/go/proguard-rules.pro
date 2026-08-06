# PhpNitro Go has no JS bridge or reflection-based dispatch of its own
# (that's :app's WebAppInterface, not used here — this app has no WebView
# at all) — CameraX and ML Kit ship their own consumer ProGuard rules
# inside their AARs, so nothing app-specific has been needed here yet. If
# a release build crashes where a debug build doesn't, start by comparing
# behavior with isMinifyEnabled = false before adding rules here.
