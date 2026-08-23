import java.util.Properties

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
    // id("com.google.gms.google-services") // push notifications, see FcmService.kt.example
}

// android/keystore.properties (gitignored — see keystore.properties.example
// for the shape) holds the release signing credentials. Its absence isn't
// an error: it only means `gradle :app:assembleRelease` will fail signing
// validation, `:app:assembleDebug` is unaffected either way.
val keystoreProperties = Properties().apply {
    val file = rootProject.file("keystore.properties")
    if (file.exists()) file.inputStream().use { load(it) }
}

android {
    namespace = "com.mobile.engine"
    compileSdk = 35

    defaultConfig {
        applicationId = "com.mobile.engine"
        minSdk = 24
        targetSdk = 35
        versionCode = 1
        versionName = "0.1"
        // Real on-device UI tests (src/androidTest) — see this module's
        // own docblock on why UI Automator, not Espresso: every screen is
        // one NativeCanvasView.onDraw() call, not a real Android View per
        // widget, so Espresso's view-matcher model has nothing to match.
        // UI Automator instead drives the same virtual accessibility node
        // tree CanvasAccessibilityNodeProvider exposes to TalkBack.
        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
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
            // addresses instead of function names. This is the SAME
            // fix android/go/build.gradle.kts carries — this file is
            // exactly what `phpx new` copies into every scaffolded
            // project (see cmdNew() in bin/phpx), so any real developer
            // publishing their own PhpNitro app hits this same warning
            // without it. SYMBOL_TABLE (not FULL): enough to
            // symbolicate a stack trace, without bundling full DWARF
            // debug info Google doesn't need.
            ndk {
                debugSymbolLevel = "SYMBOL_TABLE"
            }
        }
    }

    packaging {
        jniLibs {
            // Keep libphp.so as a real extracted file so the app can exec it
            // from the native library dir (Android W^X forbids exec from
            // writable app storage).
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
    // The native render engine itself — NativeRenderPocActivity,
    // NativeCanvasView, PhpServer, MainActivity's WebView pipeline, every
    // Device/*Receiver, libphp.so/libsqlite3.so, and every dependency the
    // engine's own code needs (biometric, billing, maps, translate,
    // lottie...) come in transitively as `api` dependencies of :engine —
    // see android/engine/build.gradle.kts. A project scaffolded via
    // `phpx new` instead depends on the published com.phpnitro:engine
    // artifact; this project() dependency is this monorepo's own
    // "developing the framework itself" path.
    implementation(project(":engine"))

    // Push notifications — uncomment together with the plugin above and
    // google-services.json, see FcmService.kt.example.
    // implementation(platform("com.google.firebase:firebase-bom:33.5.1"))
    // implementation("com.google.firebase:firebase-messaging")

    // E2E tests only (src/androidTest) — never shipped in a real APK.
    androidTestImplementation("androidx.test.ext:junit:1.2.1")
    androidTestImplementation("androidx.test:runner:1.6.2")
    androidTestImplementation("androidx.test:rules:1.6.1")
    androidTestImplementation("androidx.test.uiautomator:uiautomator:2.3.0")
}
