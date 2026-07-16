plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
    // id("com.google.gms.google-services") // push notifications, see FcmService.kt.example
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
    }

    buildTypes {
        release {
            isMinifyEnabled = false
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
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("androidx.biometric:biometric:1.1.0")

    // Push notifications — uncomment together with the plugin above and
    // google-services.json, see FcmService.kt.example.
    // implementation(platform("com.google.firebase:firebase-bom:33.5.1"))
    // implementation("com.google.firebase:firebase-messaging")
}
