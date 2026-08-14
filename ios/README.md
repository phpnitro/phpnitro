# iOS — deux chantiers séparés, aucun compilé sur un vrai Mac pour l'instant

Ce dossier est maintenant un vrai Swift Package (`Package.swift`) avec **deux cibles indépendantes**, chacune l'équivalent iOS d'un module Android différent :

- **`PhpNitroWebViewBridge`** (`Sources/PhpNitroWebViewBridge/`) — le repli `WKWebView`, équivalent de `MainActivity.kt`/`WebAppInterface.kt`. Écrit avant que ce `Package.swift` existe (voir plus bas) ; le déplacer ici ne change aucune ligne, ça le rend juste compilable via `xcodebuild` pour la première fois (voir `.github/workflows/ci.yml`, job `ios-build`).
- **`PhpNitroNativeEngine`** (`Sources/PhpNitroNativeEngine/`) — le vrai chantier ajouté avec ce manifeste : la contrepartie iOS du moteur de rendu natif PRINCIPAL de ce framework (`android/engine`'s `NativeCanvasView.kt`), pas le repli WebView. Décode le même JSON que produit `Engine\Native\Canvas::toJson()` (`DrawCommand.swift`) et le rejoue avec Core Graphics (`NativeCanvasView.swift`). **A de vrais tests unitaires** (`Tests/PhpNitroNativeEngineTests/`, y compris un décodage d'une fixture golden-file réelle copiée depuis `packages/ui/tests/Golden/__fixtures__/button_with_icon.json`) qui tournent réellement en CI (`xcodebuild ... test` sur `macos-14`) — la première chose dans tout ce dossier `ios/` à être réellement vérifiée par une machine, pas seulement relue par un humain.

**Rien de tout ça n'a tourné sur un simulateur ou un device réel** — écrit et (pour `PhpNitroNativeEngine`) testé unitairement via CI, mais jamais ouvert dans Xcode ni lancé interactivement. Traite ce dossier comme du code source réel et sérieusement écrit, pas comme une app fonctionnelle.

## PhpNitroWebViewBridge — ce qui existe et pourquoi c'est du vrai travail (pas un stub minimal)

- `App/AppDelegate.swift` — démarre la fenêtre, pousse `ViewController`, appelle le cycle de vie de `PhpEmbedBridge`. **Hors du Swift Package** (`ios/App/`, pas `ios/Sources/`) : `@main`/le cycle de vie complet d'une app UIKit a besoin d'une vraie cible App Xcode pour tourner, ce qu'un target librairie SPM ne peut pas héberger lui-même — voir la section CI plus bas.
- `Sources/PhpNitroWebViewBridge/ViewController.swift` — équivalent de `MainActivity.kt` : une `WKWebView` plein écran, configurée avec :
  - Le pont natif (`window.iOSNative`) injecté en `WKUserScript`, avec **exactement les mêmes noms de méthodes** que `window.AndroidNative` — `assets/js/device.js`/`dialogs.js` détectent déjà `window.AndroidNative || window.iOSNative`, donc **aucun changement JS supplémentaire n'est nécessaire** pour qu'un widget/service fonctionne sur les deux plateformes.
  - `allowsBackForwardNavigationGestures = true` — équivalent du câblage du bouton retour matériel Android (`OnBackPressedCallback` dans `MainActivity.kt`) : le swipe-depuis-le-bord iOS traverse l'historique `history.pushState` que `nav.js` construit à chaque navigation partielle.
  - Permission caméra/micro pour `getUserMedia` (`WKUIDelegate.webView(_:requestMediaCapturePermissionFor:...)`, iOS 15+).
- `Sources/PhpNitroWebViewBridge/WebAppInterface.swift` — le vrai pont natif, capacité par capacité, miroir de `WebAppInterface.kt` :

  | Capacité | Android | iOS (ce fichier) |
  |---|---|---|
  | Vibreur | `VibrationEffect.createOneShot(ms, ...)` — durée exacte honorée | `UIImpactFeedbackGenerator` — **durée ignorée**, iOS n'expose aucune API de vibration à durée variable aux apps tierces (limite plateforme réelle, pas un oubli) |
  | Photo native | `ActivityResultContracts.TakePicturePreview` | `UIImagePickerController` (source caméra) |
  | Sélecteur d'image | `ActivityResultContracts.GetContent` | `PHPickerViewController` (iOS 14+, ne demande **aucune** permission photothèque) |
  | Biométrie | `BiometricPrompt` | `LocalAuthentication`/`LAContext` (Face ID/Touch ID) |
  | Son | `MediaPlayer` | `AVPlayer` (streaming direct depuis l'URL, pas de téléchargement préalable) |
  | Notification | `NotificationCompat` | `UNUserNotificationCenter` (locale, hors-ligne) |
  | Impression | `WebView.createPrintDocumentAdapter` + `PrintManager` | `WKWebView.viewPrintFormatter()` + `UIPrintInteractionController` |
  | Alertes/confirmations | `AlertDialog` | `UIAlertController` |

  Chaque callback JS (`window.onNativePhotoTaken`, `onNativeBiometricResult`, `onNativeImagePicked`, `onNativeConfirmResult`) est identique aux deux plateformes — device.js/dialogs.js n'ont jamais besoin de savoir sur quelle plateforme ils tournent.
- `App/Info.plist` — permissions caméra/micro/localisation/Face ID (équivalent des `<uses-permission>` Android), + exception ATS pour le HTTP local en dev.

## Ce qui manque réellement : le PHP embarqué

**C'est le seul morceau non résolu, et le plus gros.** `Sources/PhpNitroWebViewBridge/PhpEmbedBridge.swift` documente précisément pourquoi et comment :

- L'idée initiale de ce projet (cross-compiler `php -S` pour iOS puis le lancer en sous-processus via `Process`/`NSTask`, comme `PhpServer.kt` le fait sur Android via `ProcessBuilder`) est **fausse** — `Process`/`NSTask` n'existe tout simplement pas sur iOS (uniquement macOS), sandbox Apple oblige. Cette erreur était dans une version précédente de ce README ; elle est corrigée ici.
- La bonne approche : le SAPI **embed** de PHP (`--enable-embed=static` à la compilation de php-src), qui produit une bibliothèque statique liée directement dans l'app — PHP tourne **dans le même process** que l'app iOS, pas dans un processus séparé.
- Le service au WebView se ferait via `WKURLSchemeHandler` (intercepter un schéma custom comme `phpx://`, pas un vrai serveur HTTP sur `127.0.0.1`) plutôt qu'une boucle serveur socket — plus naturel et plus proche des APIs iOS que de réimplémenter un serveur HTTP en C à l'intérieur du SAPI embed.

`PhpEmbedBridge.swift` est un **squelette d'architecture avec des `TODO` explicites**, pas du code qui compile — les flags exacts de build de php-src pour `arm64-apple-ios`, le header de pont C/Objective-C nécessaire (Swift ne peut pas appeler directement certaines macros C de php-src), et la traduction requête/réponse `WKURLSchemeTask` ↔ superglobales PHP n'ont jamais pu être vérifiés sans Mac/toolchain Xcode/build php-src disponible ici.

## Prochaines étapes, dans l'ordre, pour quelqu'un avec un Mac

1. **Valider le pont natif d'abord, sans PHP embarqué.** Créer un vrai projet Xcode (File → New → Project → App, UIKit, Swift), y glisser les fichiers de `App/` et `Sources/PhpNitroWebViewBridge/` ci-dessus, remplacer `YOUR_COMPUTER_LAN_IP` par l'IP LAN réelle de la machine qui fait tourner `php bin/phpx serve`. Vérifier que la page se charge, que `window.iOSNative` existe (`console.log` depuis Safari Web Inspector connecté au simulateur), et que chaque capacité (vibreur, biométrie, notification, appareil photo, sélecteur d'image, son, impression, dialogues) fonctionne réellement sur un vrai iPhone.
2. **Seulement ensuite**, s'attaquer à `PhpEmbedBridge.swift` : cross-compiler php-src pour `arm64-apple-ios` (device) et les cibles simulateur, écrire le header de pont, implémenter `webView(_:start:)` pour de vrai.
3. Retirer `YOUR_COMPUTER_LAN_IP` et le schéma `http://` une fois `PhpEmbedBridge` fonctionnel, au profit du schéma `phpx://` embarqué.
4. Porter `bin/phpx bundle:android`'s logique de copie (`public/`, `lib/`, `packages/`, `composer.json`) vers une phase "Copy Bundle Resources" Xcode équivalente, packagée dans l'`.ipa`.

## PhpNitroNativeEngine — la contrepartie du VRAI chemin de rendu, pas du repli WebView

Tout ce qui précède porte le repli WKWebView (`android/app`'s `MainActivity.kt`) — mais `NativeRenderPocActivity`/`NativeCanvasView.kt` sont devenus le chemin PRINCIPAL de ce framework il y a longtemps déjà (voir leur propre docblock : "the app's real launcher now, not an opt-in preview"). Jusqu'à ce `Package.swift`, **iOS n'avait absolument aucune connaissance de ce protocole** — cette cible comble exactement ça, pas la coquille WebView plus haut.

- `Sources/PhpNitroNativeEngine/DrawCommand.swift` — un décodeur `Codable` du JSON que `Engine\Native\Canvas::toJson()` produit déjà (le même protocole que `NativeCanvasView.kt` consomme côté Android) : `rect`/`text`/`icon`/`circle`/`line`/`arc`, les primitives "phase 0" — un type de commande non reconnu (`image`, `clientPanel`, `hScroll`/`vScroll`, `slider`, `skeleton`, `spinner`, `custom:*`) se décode en `.unknown(type:)` plutôt que de lever une exception, même contrat de résilience que `registerCustomCommandHandler()` côté Kotlin.
- `Sources/PhpNitroNativeEngine/NativeCanvasView.swift` — un `UIView` qui rejoue ces commandes avec Core Graphics dans `draw(rect:)`. Aucune gestion tactile/hit-region, aucun scroll, aucune transition Hero, aucune vue superposée (VideoPlayer/MapView/Lottie) — ça prouve que le protocole JSON se rejoue correctement sur iOS, tout le reste interactif est un chantier séparé, réel, pas encore attaqué.
- `Tests/PhpNitroNativeEngineTests/DrawCommandTests.swift` — **de vrais tests unitaires**, y compris un décodage d'une fixture golden-file copiée verbatim depuis `packages/ui/tests/Golden/__fixtures__/button_with_icon.json` côté PHP : si `Canvas.php` change de forme JSON d'une façon qui casse ce décodeur, c'est un changement qu'un humain ferait exprès (en mettant à jour la fixture golden ET cette copie), pas une régression silencieuse.

**Fait depuis** : rendu réel des icônes (Core Text, `IconFont.swift`), hit-testing (`DrawCommandPayload.action(at:)`, câblé sur un vrai `UITapGestureRecognizer` dans `NativeCanvasView.swift`), une première vague de parité des commandes de dessin avec `NativeCanvasView.kt` — `image` (`ImageLoader.swift`, décodage + cache + fetch réseau via `URLSession`, y compris les URIs `data:` d'une photo capturée/choisie), `spinner` et `skeleton` (animés via `CADisplayLink`, même idée que les `ValueAnimator` démarrés/arrêtés à la demande côté Kotlin) —, et une VRAIE boucle de fetch réseau : `ScreenClient.swift` (l'équivalent minimal de `fetchDrawCommands()` : un `GET /native/layout-demo?screen=&width=&height=&action=`, décode soit un `DrawCommandPayload`, soit l'enveloppe `{"error":{...}}` que `public/index.php` renvoie) et `NativeScreenViewController.swift` (héberge un `NativeCanvasView`, fetch au chargement, refetch sur chaque tap via `onAction`).

**Ce qui manque encore ici, dans l'ordre de priorité** :
1. Aucun binaire PHP embarqué (même lacune que `PhpNitroWebViewBridge` — voir plus haut, aucun raccourci ici).
2. `ScreenClient`/`NativeScreenViewController` sont la tranche MINIMALE de `fetchDrawCommands()`/`NativeRenderPocActivity.kt` — pas de pile d'écrans/retour arrière, pas de court-circuit `lastHash` (chaque refetch retélécharge tout), pas de params `dark`/`locale`/`online`/`scroll_y`, pas de champs de formulaire, pas de polling (`Async`/`Canvas::pollAgain()`), pas de `redirect`/`confetti`/`snackbar`, pas d'écran d'erreur réel (un fetch raté est un no-op silencieux pour l'instant).
3. Encore des types de commande non portés : `clientPanel`, `hScroll`/`vScroll`, `slider`, `custom:*` — tous encore `.unknown(type:)` côté iOS (no-op silencieux, pas un crash).
4. Aucun overlay pour les Views natives (VideoPlayer, MapView, Lottie, TextField/EditText) — le pendant de `rootLayout.addView(...)` dans `NativeRenderPocActivity.kt`.

## PhpNitroGo — l'équivalent iOS d'`android/go/`

`Sources/PhpNitroGo/` — un écran de connexion (`ConnectViewController`, `HostPort.swift`) qui valide une saisie `IP:PORT` et, par défaut, pousse un vrai `NativeScreenViewController` (voir plus haut) pointé dessus — même comportement que `renderIntent()`/`ConnectActivity.kt` côté Android : taper "Connecter" mène au vrai écran distant, pas juste à une closure qui ne fait rien. `navigatesAutomatically` (true par défaut) permet de désactiver cette navigation automatique pour un appelant qui veut la piloter lui-même, ou pour un test qui ne veut pas déclencher un vrai fetch réseau. Contrairement à `android/go/`, **pas encore de scan QR** (pas de session `AVFoundation`) — saisie manuelle seulement, même limitation de départ qu'avait `android/go/` avant `ScanActivity`. `HostPort.parse(_:)` a de vrais tests unitaires (`Tests/PhpNitroGoTests/HostPortTests.swift`), et `ScreenClient` a les siens contre un vrai `URLProtocol` stub — pas de serveur `phpx serve` réel joignable depuis la CI, mais le pipeline URLSession/JSONDecoder réel est exercé, pas juste compilé. Vérifiés en CI comme le reste.

**Vérifié pour de vrai, pour la première fois dans ce dossier** : `.github/workflows/ci.yml`'s job `ios-build` (runner `macos-14`) compile `PhpNitroWebViewBridge` et compile+exécute les tests de `PhpNitroNativeEngine` à chaque push — `xcodebuild -scheme <cible> ...` fonctionne directement contre ce `Package.swift`, aucun `.xcodeproj` à committer (Xcode sait ouvrir/construire un paquet SPM brut par nom de scheme depuis Xcode 11). Si ce job casse, c'est une vraie régression de compilation, pas juste "jamais vérifié".
