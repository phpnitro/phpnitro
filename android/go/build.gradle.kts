plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

android {
    namespace = "com.phpnitro.go"
    compileSdk = 35

    defaultConfig {
        applicationId = "com.phpnitro.go"
        minSdk = 24
        targetSdk = 35
        versionCode = 1
        versionName = "0.1"
    }

    packaging {
        jniLibs {
            // Required as long as :engine embeds libphp.so, even though
            // remote mode (the only mode this app has) never execs it —
            // known v1 limitation, see the "hors scope" list in this
            // feature's plan. Without this, packaging fails outright
            // (same reason :app declares it — see that module's own
            // comment).
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
    // NativeRenderPocActivity/NativeCanvasView, unmodified — this app
    // supplies no PHP, no bundled project code, just the "serverHost"/
    // "serverPort" intent extras ConnectActivity fills in from user input.
    // See NativeRenderPocActivity.kt's remote-mode branch in onCreate().
    implementation(project(":engine"))

    // QR scan (ScanActivity) — CameraX for the live preview + frame feed,
    // ML Kit for decoding. :engine already depends on ML Kit's translate
    // module for a different feature (Engine\Device\Translator); this is
    // ML Kit's separate barcode-scanning module, on-device, no network
    // call, no Google account needed.
    val cameraxVersion = "1.4.1"
    implementation("androidx.camera:camera-core:$cameraxVersion")
    implementation("androidx.camera:camera-camera2:$cameraxVersion")
    implementation("androidx.camera:camera-lifecycle:$cameraxVersion")
    implementation("androidx.camera:camera-view:$cameraxVersion")
    implementation("com.google.mlkit:barcode-scanning:17.3.0")
}
