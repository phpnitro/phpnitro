import java.util.Properties

plugins {
    id("com.android.application")
    // AGP 9.0+ bundles Kotlin support directly (no separate
    // org.jetbrains.kotlin.android plugin) — see
    // developer.android.com/build/migrate-to-built-in-kotlin.
}

// android/go/keystore.properties (gitignored — see keystore.properties.example
// for the shape) holds this module's OWN release signing credentials —
// a dedicated keystore, separate from :app's, since com.phpnitro.go and
// com.mobile.engine are different Play Store listings with different
// identities. Its absence isn't an error: it only means
// `gradle :go:assembleRelease` will fail signing validation,
// `:go:assembleDebug` is unaffected either way.
val keystoreProperties = Properties().apply {
    val file = file("keystore.properties")
    if (file.exists()) file.inputStream().use { load(it) }
}

android {
    namespace = "com.phpnitro.go"
    compileSdk = 36

    defaultConfig {
        applicationId = "com.phpnitro.go"
        minSdk = 24
        targetSdk = 36
        // Bumped from 2/"0.2" straight to 4/"0.4" — Play Console permanently
        // consumes a versionCode the moment a real upload attempt reaches
        // it (even one still in draft/setup), and 3 was already consumed
        // by an earlier upload before the two crash fixes above landed
        // (WorkManager's WorkDatabase_Impl, ML Kit's DI graph) — this is
        // the first build that actually contains both, so it needs a
        // strictly higher versionCode than whatever the Store already has.
        versionCode = 4
        versionName = "0.4"
    }

    signingConfigs {
        create("release") {
            if (keystoreProperties.isNotEmpty()) {
                storeFile = file(keystoreProperties.getProperty("storeFile"))
                storePassword = keystoreProperties.getProperty("storePassword")
                keyAlias = keystoreProperties.getProperty("keyAlias")
                keyPassword = keystoreProperties.getProperty("keyPassword")
            }
        }
    }

    buildTypes {
        release {
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
            if (keystoreProperties.isNotEmpty()) {
                signingConfig = signingConfigs.getByName("release")
            }
            // Play Console warns on any App Bundle shipping native code
            // (libphp.so/libsqlite3.so, both prebuilt and committed as-is
            // — see android/README.md) without a debug symbols upload —
            // without this, a native crash/ANR report shows raw memory
            // addresses instead of function names. SYMBOL_TABLE (not
            // FULL): enough to symbolicate a stack trace, without
            // bundling full DWARF debug info Google doesn't need.
            ndk {
                debugSymbolLevel = "SYMBOL_TABLE"
            }
        }
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

    // No kotlinOptions block: built-in Kotlin derives jvmTarget from
    // compileOptions.targetCompatibility above (17) by default.
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
