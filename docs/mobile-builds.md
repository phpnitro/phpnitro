# Builds mobiles

## Android — vérifié de bout en bout sur device réel

L'app Android embarque un **vrai PHP cross-compilé** (via le NDK, `armeabi-v7a` et `arm64-v8a` déjà fournis dans `android/engine/src/main/jniLibs/` — **aucun Docker ni compilation requise** pour builder l'app). Au lancement, `PhpServer.kt` copie l'app PHP vers `filesDir`, démarre le binaire embarqué sur un port choisi dynamiquement, et `NativeRenderPocActivity` s'y connecte en HTTP pour récupérer les commandes de dessin JSON, rejouées sur un vrai `android.graphics.Canvas` (pas de WebView dans ce chemin — voir `PhpNitroWebViewBridge`/`MainActivity.kt` plus bas pour l'ancien repli historique) : **PHP tourne réellement sur le téléphone**, pas sur un serveur distant.

Un vrai splash screen natif (Android 12+ SplashScreen API) reste affiché jusqu'à ce que le serveur PHP ait démarré et que la page ait fini de charger.

```bash
php bin/phpx build:android   # bundle: + gradle :app:assembleDebug, en une commande
# → android/app/build/outputs/apk/debug/app-debug.apk
```

Aucun prérequis à installer à la main : `build:android` détecte JDK 17+/Gradle ≥ 9.1/le SDK Android (compileSdk 36) déjà présents, et télécharge tout seul ce qui manque (JDK Temurin, Gradle, SDK command-line tools — mis en cache dans `~/.local/share/phpnitro-tools/`, jamais re-téléchargé une fois fait). `php bin/phpx doctor` liste l'état de chaque outil sans rien installer, si tu veux juste vérifier avant de lancer une vraie build. Pour régénérer les binaires PHP toi-même, voir `android/README.md`.

Installation sur téléphone : `adb install -r app-debug.apk`, ou transfère le fichier et autorise l'installation de sources inconnues. APK signé en debug (parfait pour tester, pas pour le Play Store — il faudra une clé de release).

**Vérifié en conditions réelles** sur un Infinix X6532 (Android 14, `armeabi-v7a`) : navigation complète, biométrie, paiements en mode démo, animations, deep linking, partage natif, changement d'icône dynamique, alarme planifiée, accessibilité TalkBack.

### Mesures de performance (device réel, non comparées à Flutter/RN)

| Mesure | Valeur |
|---|---|
| Taille APK debug | ~11,4 Mo |
| Démarrage à froid | 1,3–2,0 s |
| Démarrage à chaud | ~232 ms |
| Mémoire (PSS) | ~146 Mo |

### App Links HTTPS réels (au lieu du schéma `phpnitro://`)

`AndroidManifest.xml` déclare déjà un `<intent-filter android:autoVerify="true">` pour `https://app.example.com` — remplace `app.example.com` par ton vrai domaine, puis :
1. Copie `docs/assetlinks.json.example` vers `https://ton-domaine/.well-known/assetlinks.json` (hébergé, en HTTPS).
2. Remplace `REPLACE_WITH_YOUR_RELEASE_KEYSTORE_SHA256_FINGERPRINT` par l'empreinte réelle : `keytool -list -v -keystore android/app/release.keystore -alias phpnitro` (champ SHA256).
3. Réinstalle l'app — Android vérifie le fichier au moment de l'installation, pas à chaque lancement.

Tant que ce fichier n'est pas hébergé avec la bonne empreinte, ce lien ne sera jamais un vrai App Link cliquable — le schéma `phpnitro://` reste le seul qui fonctionne.

### Erreurs en développement

`APP_DEBUG` (dans `.env`) est copié tel quel dans le bundle Android — mets-le à `true` pendant que tu développes pour voir la classe/message/fichier/trace complète de toute erreur directement dans l'app, pas juste "Erreur interne". Repasse-le à `false` avant une vraie release.

## iOS — vérifié sur simulateur et sur device réel, PHP réellement embarqué

`ios/` est un vrai Swift Package avec plusieurs cibles :

- **`PhpNitroNativeEngine`** — la contrepartie iOS du moteur de rendu PRINCIPAL (`android/engine`'s `NativeCanvasView.kt`), pas un repli WebView : décode le même JSON `Canvas::toJson()` et le rejoue avec Core Graphics. Vrais tests unitaires en CI (`xcodebuild ... test` sur `macos-14`) — icônes, images, `spinner`/`skeleton`, `clientPanel`/`hScroll`/`vScroll`/`slider`, hit-testing, une vraie boucle de fetch réseau/pile d'écrans, et des overlays réels pour `focus:`/`video:play:`/`map:open:` (`UITextField`/`UITextView`, `AVPlayerLayer`, `MKMapView`). **Lancé réellement sur simulateur (via `HostApp`, un harnais Xcode généré par XcodeGen) et sur un iPhone 11 Pro Max physique** (2026-09-09/10, premier Mac disponible pour ce projet) : navigation, scroll, DevTools/inspecteur, icône dynamique, et **42 des 44 capacités `device:*` concrètement portables** — voir `ios/README.md` pour la liste exacte capacité par capacité (seuls NFC — codé mais bloqué par le compte Apple Developer gratuit — et Google Sign-In — jamais configuré même côté Android — restent).
- **`PhpNitroGo`** — le module de connexion (scan QR / adresse manuelle), équivalent d'`android/go` — lui aussi lancé réellement sur simulateur (`GoHostApp`) avec parité visuelle vérifiée côté à côté avec Android.
- **`PhpNitroWebViewBridge`** — le repli `WKWebView` historique, équivalent de `MainActivity.kt`/`WebAppInterface.kt` côté Android (`window.iOSNative`, mêmes méthodes que `window.AndroidNative`). Compile en CI mais n'a jamais tourné sur un simulateur/device — non prioritaire tant que `PhpNitroNativeEngine` progresse.
- **`RustNativeRenderer`** — liaisons Swift/C vers le moteur de rendu Rust partagé, cross-compilé pour `aarch64-apple-ios-sim` en CI (le runner `macos-14` est Apple Silicon).

**Le PHP embarqué existe désormais réellement**, sur le chemin `PhpNitroNativeEngine` (pas le repli WebView) : `PhpEmbedRuntime.swift` pilote un vrai SAPI **embed** de PHP (`libphp.xcframework`, vendorisé pour device + simulateur, compilé en PHP 8.4.2 avec mbstring/filter/PCRE JIT/session/PDO-SQLite activés) via un shim C (`CPhpEmbed`) — `EmbeddedScreenDataSource` sert les écrans directement depuis ce runtime embarqué, sans réseau, en plus du chemin réseau existant (`ScreenDataSource` choisit l'un ou l'autre). Vérifié par 4 jobs CI dédiés (`ci.yml`, préfixe "iOS PHP embed") : cross-compilation de `libphp.a`, smoke test du SAPI embed sur host macOS natif, smoke test sur cible simulateur iOS, et un smoke test de liaison pour un vrai device iOS (build seulement). `bin/phpx bundle:ios` (nouvelle commande) copie `public/`/`lib/`/`packages/` dans `Resources/www` de la cible, même principe que `bundle:android`.

Voir `ios/README.md` pour l'état exact, capacité par capacité, et l'ordre recommandé des prochaines étapes.

## Linux / macOS / Windows

Les trois existent réellement, chacun avec sa propre coquille native consommant soit un moteur de rendu Rust partagé (`rust/phpnitro-render/`) soit un rendu natif dédié — voir chaque README pour le détail à jour :

- **[Linux](../linux/README.md)** — le plus abouti et le plus vérifié : GTK4/Cairo, plus de 80 tests réels tournant réellement en CI (rendu pixel par pixel, hit-testing, drag hScroll/vScroll/slider, overlays TextField/VideoPlayer), moteur Rust en rendu par défaut.
- **[macOS](../macos/README.md)** — deux chemins (Core Graphics historique, app Rust dédiée), compile et teste unitairement en CI (`macos-14`) mais jamais lancé sur un vrai Mac.
- **[Windows](../windows/README.md)** — le moteur Rust partagé est l'unique chemin de rendu (pas de GDI+/Direct2D écrit spécifiquement) ; compile en CI (dont un vrai runner `windows-latest` pour l'app WinForms) mais jamais lancé sur une vraie machine Windows.

Aucun des trois n'a de packaging livrable à un utilisateur final (`.deb`/AppImage, `.app` signé, installeur MSI) ni de runtime PHP portable embarqué — chacun lance le `php` déjà installé sur la machine, comme `bin/phpx serve` le fait déjà pour le dev local.
