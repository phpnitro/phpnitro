plugins {
    id("com.android.library")
    id("org.jetbrains.kotlin.android")
}

android {
    namespace = "com.phpnitro.engine"
    compileSdk = 35

    defaultConfig {
        minSdk = 24
        targetSdk = 35
    }

    packaging {
        jniLibs {
            // Keep libphp.so as a real extracted file so a consuming app can
            // exec it from the native library dir (Android W^X forbids exec
            // from writable app storage) — the actual APK-level packaging
            // decision is made by whichever app module depends on this
            // library, so this same setting must ALSO be declared there
            // (see android/app/build.gradle.kts).
            useLegacyPackaging = true
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }
}

dependencies {
    // Every dependency below is `api`, not `implementation` — the engine's
    // own compiled Kotlin (NativeDeviceBridge.kt, WebAppInterface.kt,
    // NativeCanvasView.kt) references these libraries' classes directly
    // (BiometricPrompt, EncryptedSharedPreferences, WorkManager,
    // BillingClient, FusedLocationProviderClient, osmdroid's MapView,
    // ML Kit's Translator, LottieAnimationView), not reflectively — Gradle
    // has no lighter-weight "only on the classpath if the app actually
    // calls this" mechanism, so a consuming app needs every one of these
    // on its own runtime classpath regardless of whether that particular
    // app's screens ever exercise the capability. Same tradeoff Flutter's
    // own engine makes (one monolithic engine binary, not "pay only for
    // what you use").
    api("androidx.core:core-ktx:1.13.1")
    api("androidx.appcompat:appcompat:1.7.0")
    api("androidx.biometric:biometric:1.1.0")
    api("androidx.core:core-splashscreen:1.0.1")
    api("androidx.security:security-crypto:1.1.0-alpha06")
    api("androidx.work:work-runtime-ktx:2.9.1")
    api("com.android.billingclient:billing-ktx:7.1.1")
    api("com.google.android.gms:play-services-location:21.3.0")
    // A real interactive map (pan/zoom) with zero API key — same
    // OpenStreetMap tiles NativeWidgetsMapsScreen.php's static-tile
    // fallback already fetches directly, now behind a genuine MapView
    // instead of a single non-interactive image.
    api("org.osmdroid:osmdroid-android:6.1.20")
    // On-device translation — no API key, no network dependency once the
    // language model is downloaded once, unlike Engine\GoogleTranslate's
    // web-based translate.google.com widget. Genuinely more "native" than
    // what it replaces, not just a workaround.
    api("com.google.mlkit:translate:17.0.3")
    // RenderLottie — a real com.airbnb.android.lottie.LottieAnimationView
    // overlaid at the widget's rect (same "no Canvas concept for a
    // continuous animation loop, overlay a real Android View instead"
    // idiom NativeVideoPlayer/NativeMapView already use), not a hand-rolled
    // frame-by-frame Canvas replay.
    api("com.airbnb.android:lottie:6.5.2")
}
