# App Android — PHP embarqué, vérifié sur device réel

**État : fonctionnel, vérifié de bout en bout** sur un Infinix X6532 (Android 14, `armeabi-v7a`) : l'app s'installe, se lance sans crash, et affiche l'UI complète (`engine/app/HomePage.php`) rendue par un vrai processus PHP tournant sur le téléphone — pas de réseau, pas de PC requis.

## Comment ça marche

- `MainActivity` affiche une `WebView` pointée sur `http://127.0.0.1:8090/`.
- `PhpServer.kt` lance le binaire PHP embarqué (`jniLibs/<abi>/libphp.so`) comme sous-processus au démarrage de l'app, avec `LD_LIBRARY_PATH` pointé sur le dossier natif (pour trouver `libsqlite3.so`) et `TMPDIR`/session/logs dans le stockage privé de l'app.
- Le contenu de `engine/` (widgets PHP + Tailwind compilé) est copié depuis `assets/www` vers le stockage de l'app au premier lancement.
- Deux architectures embarquées (`armeabi-v7a` et `arm64-v8a`) : la bonne est choisie automatiquement par Android selon le device.

## Compiler les binaires PHP (déjà fait, généré dans `jniLibs/` — gitignored, gros fichiers)

Les binaires PHP ne sont **pas commités** (trop volumineux, ~17 Mo chacun une fois OpenSSL statiquement lié). `android/php-ndk-patch/` contient un `Dockerfile` (+ `Makefile` + patches) **modifié par rapport à l'upstream** (`v3l0c1r4pt0r/php-ndk`) : la version d'origine ne compile ni `openssl` ni `curl`, ce qui rendait **tout appel HTTPS sortant impossible** depuis le PHP embarqué sur device (confirmé en conditions réelles : Feexpay échouait avec "Undefined constant CURLOPT_POST" faute de `curl`, et même en le contournant via `file_get_contents`, `https://` n'était pas un wrapper enregistré faute d'`openssl`). Notre version ajoute OpenSSL 3.0.15 cross-compilé statiquement (`no-shared`, cible officielle `android-arm`/`android-arm64`) et le câble via `--with-openssl` — voir le commentaire en tête du `Dockerfile` pour le détail.

```bash
git clone https://github.com/v3l0c1r4pt0r/php-ndk.git /tmp/php-ndk
cp android/php-ndk-patch/* /tmp/php-ndk/   # remplace Dockerfile/Makefile par notre version avec openssl
cd /tmp/php-ndk
make armv7a && make install-armv7a DESTDIR=/tmp/php-ndk-output   # nécessite Docker, télécharge le NDK (~1 Go) + compile OpenSSL + PHP
make aarch64 && make install-aarch64 DESTDIR=/tmp/php-ndk-output
```

Puis copier et renommer (`php.so` → `libphp.so`, pour rester sur la convention `lib*.so`) dans `android/app/src/main/jniLibs/<abi>/` (et le même chemin sous `examples/ecom/android/`), avec `libsqlite3.so` à côté. Un strip (`llvm-strip`, fourni par le NDK) réduit la taille d'environ moitié sans rien casser.

**cacert.pem, indispensable en plus du binaire** : OpenSSL cross-compilé n'a aucun magasin de certificats racine à sa disposition (Android garde le sien dans un format qu'OpenSSL ne sait pas lire directement) — sans ça, toute requête HTTPS échoue quand même avec `certificate verify failed`, TLS négocié ou pas. `android/app/src/main/assets/cacert.pem` (le bundle Mozilla, celui que curl/la plupart des distros embarquent aussi) est copié une fois par lancement vers le stockage de l'app par `PhpServer.kt`, et pointé via `-d openssl.cafile=...` au démarrage du process PHP.

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
- **Aucun appel HTTPS sortant ne fonctionnait depuis le PHP embarqué** (trouvé en testant Feexpay en conditions réelles, `SANDBOX=false`, une vraie clé de prod) : le binaire `php-ndk` upstream n'a ni `curl` ni `openssl` — confirmé directement sur device (`php -m`, `stream_get_wrappers()`). Ça cassait silencieusement Feexpay (SDK vendeur basé sur `curl_*`, échec immédiat avec "Undefined constant CURLOPT_POST", avalé par son propre `try/catch`) mais aussi, plus largement, tout `file_get_contents('https://...')` du reste du framework (Stripe, OAuth, Firebase) — jamais vérifié sur device avant. Fixé en cross-compilant OpenSSL statiquement dans le binaire (voir section ci-dessus) + `cacert.pem` embarqué. **Vérifié pour de vrai** : appel réel contre `api-v2.feexpay.me` depuis le binaire tournant sur l'Infinix X6532, `HTTP/1.1 200 OK`.
- **Feexpay rejette un `amount` non entier** ("Validation failed" / "amount must be an integer number") — trouvé seulement en envoyant un vrai `POST` une fois HTTPS réparé. `Engine\Payments\Feexpay` arrondit maintenant explicitement avant l'envoi (`(int) round($amount)`), le paramètre public reste `float` pour rester cohérent avec les autres gateways du package.

## Permissions et capacités device

`MainActivity` demande les permissions caméra/micro/localisation et configure la `WebView` (`WebChromeClient.onPermissionRequest`, `onGeolocationPermissionsShowPrompt`, `setGeolocationEnabled`) pour que `Device\Vibrate`, `LocationButton`, `Device\Camera` et `Device\Microphone` fonctionnent réellement.

## Le moteur de rendu Rust partagé : `RustRenderer.kt` — compile, jamais exécuté

`engine/src/main/java/com/phpnitro/engine/RustRenderer.kt` appelle `rust/phpnitro-render/src/jni_bridge.rs` (un pont JNI dédié — convention d'appel différente de l'ABI C plate que Linux/Windows/macOS/iOS consomment directement via ctypes/P-Invoke/C-interop). Pendant Android de ces quatre autres ports, mais avec une vraie limite : **`android-e2e-test`, le seul job CI qui exécute du code sur un vrai émulateur, est désactivé** (limite de facturation GitHub — voir le commentaire dans `ci.yml`). Contrairement à Linux/Windows/macOS/iOS (chacun a eu une preuve d'exécution réelle via CI), rien ici n'a jamais été appelé par une vraie JVM.

Conséquence assumée : **`RustRenderer` n'est PAS câblé dans `NativeCanvasView.kt`** — il reste une classe additive, du code mort du point de vue de l'app livrée, tant qu'il n'est pas génuinement vérifiable. Ce que le job CI `android-build` prouve réellement : `rust/phpnitro-render` cross-compile pour `arm64-v8a`/`armeabi-v7a` (via `cargo-ndk`, placé directement dans `jniLibs/<abi>/` — jamais commité, contrairement à `libphp.so`, puisque reconstruire ce `.so` en CI ne coûte que quelques crates, pas tout un interpréteur PHP cross-compilé) et que `RustRenderer.kt`/`jni_bridge.rs` s'accordent au niveau des types/symboles JNI (`gradle :app:assembleDebug` compile et lie tout ça) — pas qu'un seul appel fonctionne réellement une fois chargé.

Piège JNI réel évité, documenté dans `RustRenderer.kt` lui-même : toutes les méthodes `external fun` sont déclarées **statiques** (`companion object`), pas des méthodes d'instance — parce que `jni_bridge.rs` prend un `JClass` (pas un `JObject`) comme second paramètre partout. Une méthode d'instance aurait fait passer un `jobject` (`this`) à cet emplacement à la place, un vrai décalage silencieux qu'aucun outil ici n'aurait détecté avant un vrai crash sur device.
