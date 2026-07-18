# Coquille iOS — bridge natif écrit, PHP embarqué toujours manquant

Équivalent iOS de `android/` : une `WKWebView` native affichant l'UI servie par PHP. **Rien dans ce dossier n'a été compilé ni lancé** — aucun Mac/Xcode disponible dans cet environnement de développement. Traite ce dossier comme du code source réel et sérieusement écrit, pas comme une app fonctionnelle : il doit être ouvert dans Xcode, compilé, et débogué comme n'importe quel nouveau code avant de faire confiance à la moindre ligne.

## Ce qui existe et pourquoi c'est du vrai travail (pas un stub minimal)

- `App/AppDelegate.swift` — démarre la fenêtre, pousse `ViewController`, appelle le cycle de vie de `PhpEmbedBridge`.
- `App/ViewController.swift` — équivalent de `MainActivity.kt` : une `WKWebView` plein écran, configurée avec :
  - Le pont natif (`window.iOSNative`) injecté en `WKUserScript`, avec **exactement les mêmes noms de méthodes** que `window.AndroidNative` — `assets/js/device.js`/`dialogs.js` détectent déjà `window.AndroidNative || window.iOSNative`, donc **aucun changement JS supplémentaire n'est nécessaire** pour qu'un widget/service fonctionne sur les deux plateformes.
  - `allowsBackForwardNavigationGestures = true` — équivalent du câblage du bouton retour matériel Android (`OnBackPressedCallback` dans `MainActivity.kt`) : le swipe-depuis-le-bord iOS traverse l'historique `history.pushState` que `nav.js` construit à chaque navigation partielle.
  - Permission caméra/micro pour `getUserMedia` (`WKUIDelegate.webView(_:requestMediaCapturePermissionFor:...)`, iOS 15+).
- `App/WebAppInterface.swift` — le vrai pont natif, capacité par capacité, miroir de `WebAppInterface.kt` :

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

**C'est le seul morceau non résolu, et le plus gros.** `App/PhpEmbedBridge.swift` documente précisément pourquoi et comment :

- L'idée initiale de ce projet (cross-compiler `php -S` pour iOS puis le lancer en sous-processus via `Process`/`NSTask`, comme `PhpServer.kt` le fait sur Android via `ProcessBuilder`) est **fausse** — `Process`/`NSTask` n'existe tout simplement pas sur iOS (uniquement macOS), sandbox Apple oblige. Cette erreur était dans une version précédente de ce README ; elle est corrigée ici.
- La bonne approche : le SAPI **embed** de PHP (`--enable-embed=static` à la compilation de php-src), qui produit une bibliothèque statique liée directement dans l'app — PHP tourne **dans le même process** que l'app iOS, pas dans un processus séparé.
- Le service au WebView se ferait via `WKURLSchemeHandler` (intercepter un schéma custom comme `phpx://`, pas un vrai serveur HTTP sur `127.0.0.1`) plutôt qu'une boucle serveur socket — plus naturel et plus proche des APIs iOS que de réimplémenter un serveur HTTP en C à l'intérieur du SAPI embed.

`PhpEmbedBridge.swift` est un **squelette d'architecture avec des `TODO` explicites**, pas du code qui compile — les flags exacts de build de php-src pour `arm64-apple-ios`, le header de pont C/Objective-C nécessaire (Swift ne peut pas appeler directement certaines macros C de php-src), et la traduction requête/réponse `WKURLSchemeTask` ↔ superglobales PHP n'ont jamais pu être vérifiés sans Mac/toolchain Xcode/build php-src disponible ici.

## Prochaines étapes, dans l'ordre, pour quelqu'un avec un Mac

1. **Valider le pont natif d'abord, sans PHP embarqué.** Créer un vrai projet Xcode (File → New → Project → App, UIKit, Swift), y glisser les fichiers de `App/` ci-dessus, remplacer `YOUR_COMPUTER_LAN_IP` par l'IP LAN réelle de la machine qui fait tourner `php bin/phpx serve`. Vérifier que la page se charge, que `window.iOSNative` existe (`console.log` depuis Safari Web Inspector connecté au simulateur), et que chaque capacité (vibreur, biométrie, notification, appareil photo, sélecteur d'image, son, impression, dialogues) fonctionne réellement sur un vrai iPhone.
2. **Seulement ensuite**, s'attaquer à `PhpEmbedBridge.swift` : cross-compiler php-src pour `arm64-apple-ios` (device) et les cibles simulateur, écrire le header de pont, implémenter `webView(_:start:)` pour de vrai.
3. Retirer `YOUR_COMPUTER_LAN_IP` et le schéma `http://` une fois `PhpEmbedBridge` fonctionnel, au profit du schéma `phpx://` embarqué.
4. Porter `bin/phpx bundle:android`'s logique de copie (`public/`, `lib/`, `packages/`, `composer.json`) vers une phase "Copy Bundle Resources" Xcode équivalente, packagée dans l'`.ipa`.
