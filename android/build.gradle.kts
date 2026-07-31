plugins {
    id("com.android.application") version "8.7.2" apply false
    id("com.android.library") version "8.7.2" apply false
    id("org.jetbrains.kotlin.android") version "2.0.21" apply false
    // Push notifications (Firebase Cloud Messaging) — uncomment once you have
    // your own google-services.json in android/app/ (see FcmService.kt.example).
    // id("com.google.gms.google-services") version "4.4.2" apply false
}
