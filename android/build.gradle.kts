plugins {
    // 8.7.2 -> 9.0.1 : Google Play exige de cibler l'API niveau 36
    // (Android 16) d'ici le 31/08/2026 pour publier des mises à jour — la
    // série AGP 8.9.x plafonne à l'API 35, 9.0+ est la première à
    // supporter compileSdk 36 (confirmé sur la doc officielle : 9.0.1
    // supporte jusqu'à l'API 36.1, nécessite Gradle >= 9.1.0 — voir
    // bin/phpx's resolveOrInstallGradle() et .github/workflows/ci.yml).
    id("com.android.application") version "9.0.1" apply false
    id("com.android.library") version "9.0.1" apply false
    // No org.jetbrains.kotlin.android plugin: AGP 9.0+ bundles Kotlin
    // support directly ("built-in Kotlin", enabled by default) — applying
    // the separate plugin on top fails with "Cannot add extension with
    // name 'kotlin', as there is an extension already registered with
    // that name" (confirmed against a real CI run). See
    // developer.android.com/build/migrate-to-built-in-kotlin.
    // Push notifications (Firebase Cloud Messaging) — uncomment once you have
    // your own google-services.json in android/app/ (see FcmService.kt.example).
    // id("com.google.gms.google-services") version "4.4.2" apply false
}
