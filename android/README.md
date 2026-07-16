# App Android — PHP embarqué, vérifié sur device réel

**État : fonctionnel, vérifié de bout en bout** sur un Infinix X6532 (Android 14, `armeabi-v7a`) : l'app s'installe, se lance sans crash, et affiche l'UI complète (`engine/app/HomePage.php`) rendue par un vrai processus PHP tournant sur le téléphone — pas de réseau, pas de PC requis.

## Comment ça marche

- `MainActivity` affiche une `WebView` pointée sur `http://127.0.0.1:8090/`.
- `PhpServer.kt` lance le binaire PHP embarqué (`jniLibs/<abi>/libphp.so`) comme sous-processus au démarrage de l'app, avec `LD_LIBRARY_PATH` pointé sur le dossier natif (pour trouver `libsqlite3.so`) et `TMPDIR`/session/logs dans le stockage privé de l'app.
- Le contenu de `engine/` (widgets PHP + Tailwind compilé) est copié depuis `assets/www` vers le stockage de l'app au premier lancement.
- Deux architectures embarquées (`armeabi-v7a` et `arm64-v8a`) : la bonne est choisie automatiquement par Android selon le device.

## Compiler les binaires PHP (déjà fait, généré dans `jniLibs/` — gitignored, gros fichiers)

Les binaires PHP ne sont **pas commités** (trop volumineux, ~14 Mo chacun). Pour les régénérer :

```bash
git clone https://github.com/v3l0c1r4pt0r/php-ndk.git /tmp/php-ndk
cd /tmp/php-ndk
make install DESTDIR=/tmp/php-ndk-output   # nécessite Docker, télécharge le NDK (~1 Go) + compile PHP
```

Puis copier et renommer (`php.so` → `libphp.so`, pour rester sur la convention `lib*.so`) dans `android/app/src/main/jniLibs/<abi>/`, avec `libsqlite3.so` à côté. Un strip (`llvm-strip`, fourni par le NDK) réduit la taille de moitié sans rien casser.

## Build & install

```bash
cd android
php ../bin/phpx bundle:android   # copie engine/ dans app/src/main/assets/www
gradle :app:assembleDebug        # ou via Android Studio
adb install -r app/build/outputs/apk/debug/app-debug.apk
```

## Pièges rencontrés (pour référence)

- **PHP statique générique (static-php.dev) rejeté à l'installation** : ces binaires sont compilés pour Linux générique en `arm64` uniquement — sur un device 32 bits (`armeabi-v7a`, comme beaucoup de téléphones d'entrée de gamme/édition Go), Android refuse d'installer toute APK déclarant une lib native pour une ABI non supportée. Solution : cross-compiler pour les deux architectures via le NDK (voir ci-dessus), pas de binaire générique.
- **Crash immédiat au lancement (`IllegalStateException: You need to use a Theme.AppCompat theme`)** : `MainActivity` étend `AppCompatActivity` mais l'app n'avait pas de thème `Theme.AppCompat` déclaré. Fix : `android:theme="@style/Theme.AppCompat.DayNight.NoActionBar"` sur `<application>` dans `AndroidManifest.xml`.
- **`== MALI DEBUG === BAD ALLOC` dans logcat** : warnings du driver GPU Mali lors du rendu WebView, observés mais sans impact (l'app fonctionne normalement) — à surveiller si des soucis d'affichage apparaissent sur d'autres devices.
- Ne jamais lancer un émulateur Android accéléré (KVM) en parallèle de builds Docker lourds sur une machine qui peut elle-même être une VM — a provoqué un crash complet de la machine de dev pendant ce projet. Préférer un test direct sur device réel via `adb install` quand c'est possible.

## Permissions et capacités device

`MainActivity` demande les permissions caméra/micro/localisation et configure la `WebView` (`WebChromeClient.onPermissionRequest`, `onGeolocationPermissionsShowPrompt`, `setGeolocationEnabled`) pour que `VibrateButton`, `LocationButton`, `CameraPreview` et `MicrophoneButton` fonctionnent réellement.
