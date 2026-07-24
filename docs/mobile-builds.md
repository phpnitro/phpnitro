# Builds mobiles

## Android — vérifié de bout en bout sur device réel

L'app Android embarque un **vrai PHP cross-compilé** (via le NDK, `armeabi-v7a` et `arm64-v8a` déjà fournis dans `android/app/src/main/jniLibs/` — **aucun Docker ni compilation requise** pour builder l'app). Au lancement, `PhpServer.kt` copie l'app PHP vers `filesDir`, démarre le binaire embarqué sur un port choisi dynamiquement, et la WebView s'y connecte : **PHP tourne réellement sur le téléphone**, pas sur un serveur distant.

Un vrai splash screen natif (Android 12+ SplashScreen API) reste affiché jusqu'à ce que le serveur PHP ait démarré et que la page ait fini de charger.

```bash
php bin/phpx bundle:android   # copie public/ + lib/ + packages/ + composer.json (vendor --no-dev) + .env
cd android
gradle :app:assembleDebug     # ou via Android Studio
# → android/app/build/outputs/apk/debug/app-debug.apk
```

Prérequis build : Android SDK (compileSdk 35), **Gradle ≥ 8.9** (pas de wrapper commité — utilise le Gradle de ton système ou Android Studio), JDK 17. Pour régénérer les binaires PHP toi-même, voir `android/README.md`.

Installation sur téléphone : `adb install -r app-debug.apk`, ou transfère le fichier et autorise l'installation de sources inconnues. APK signé en debug (parfait pour tester, pas pour le Play Store — il faudra une clé de release).

**Vérifié en conditions réelles** sur un Infinix X6532 (Android 14, `armeabi-v7a`) : navigation complète, biométrie, paiements en mode démo, animations, deep linking, partage natif, changement d'icône dynamique, alarme planifiée, accessibilité TalkBack — voir [ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md](../ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md) pour le détail exact de ce qui a été testé et comment.

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

## iOS — pont natif écrit, jamais compilé

`ios/` contient une `WKWebView` (Swift) équivalent à la coquille Android, avec un vrai pont natif (`WebAppInterface.swift`) qui expose `window.iOSNative` avec exactement les mêmes méthodes que `window.AndroidNative` — aucun widget/service n'a besoin d'un chemin spécifique iOS.

**Rien de tout ça n'est compilé ni testé** — pas de Mac/Xcode disponible pendant son développement. Le PHP embarqué sur le device n'existe pas non plus : la bonne architecture (SAPI embed de PHP, lié statiquement, servi via `WKURLSchemeHandler`) est documentée en détail dans `ios/App/PhpEmbedBridge.swift` (squelette avec `TODO` explicites), mais aucun binaire PHP pour iOS n'a été cross-compilé.

Voir `ios/README.md` pour l'état exact, capacité par capacité, et l'ordre recommandé des prochaines étapes (compiler et tester le pont déjà écrit d'abord, contre un PHP hébergé sur le réseau, avant de toucher au SAPI embed).

## Linux / macOS / Windows

Pas implémentés — chaque plateforme desktop demanderait sa propre coquille native (GTK+WebKit, Cocoa+WKWebView, WebView2), un chantier à part entière.
