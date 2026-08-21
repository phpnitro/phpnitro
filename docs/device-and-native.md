# Capacités device & natif

Chaque capacité passe par `device:<token>` comme `$action` d'un widget natif (`Button`, `Tappable`...) — `NativeRenderPocActivity.handleDeviceAction()` (Kotlin) reconnaît le token et appelle `NativeDeviceBridge.kt` **directement**, zéro requête HTTP tant que le résultat n'a pas besoin d'être affiché. Un résultat qui doit apparaître à l'écran est écrit dans `fieldValues[name]` côté client puis renvoyé à PHP au prochain re-fetch (`refetch(action = null, includeFields = true)`) — le même mécanisme qu'une valeur de champ de formulaire.

```php
new Button('Vibrer', 'device:vibrate');
new Button('Batterie', 'device:battery:battery_out', width: ...);
// $_GET['battery_out'] au prochain rendu contient le résultat
```

Voir `lib/pages/app/NativeDeviceScreen.php` pour l'exemple complet (toutes les capacités ci-dessous, une ligne chacune).

| Token `device:` | Appelle (Kotlin) | Résultat |
|---|---|---|
| `vibrate` | `Vibrator` | — |
| `torch` | `CameraManager` (flash) | — |
| `battery:champ` | `BatteryManager` | `%` |
| `deviceid:champ` | `Settings.Secure.ANDROID_ID` | id |
| `bluetooth:champ` | `BluetoothManager` | `on`/`off`/`unsupported` |
| `securestore:clé` | Keystore-backed `EncryptedSharedPreferences` | — |
| `secureretrieve:clé:champ` | idem | valeur déchiffrée |
| `contacts:champ` | `ContentResolver` (contacts) | nombre |
| `calendar:champ` | `ContentResolver` (calendrier) | nombre d'événements à venir |
| `sound` | `MediaPlayer` (effet court) | — |
| `notify` | `NotificationCompat` | — |
| `share` | `Intent.ACTION_SEND` + `createChooser` | — |
| `appicon:clé` | deux `activity-alias` mutuellement exclusifs | — |
| `camera` | `ActivityResultContracts.TakePicturePreview` | photo |
| `pickimage` | `ActivityResultContracts.GetContent` | image choisie |
| `biometric:champ` | `BiometricPrompt` (androidx.biometric) | succès/échec |
| `brightness` | `WindowManager.LayoutParams.screenBrightness` | — |
| `locate:champ` | `FusedLocationProviderClient` | lat/lon |
| `mic:champ` | `MediaRecorder` (clip court) | statut |
| `sensor:champ` | `SensorEventListener` (lecture unique) | valeur |
| `nfcstart` / `nfcstop` | `NfcAdapter` foreground dispatch | tag lu |
| `iapquery:champ` / `iappurchase` | `BillingClient` (Play Billing) | infos produit |
| `geofenceadd` / `geofenceremove` | `GeofencingClient` | — |
| `bgschedule` / `bgcancel` | `WorkManager` | — |
| `printpdf` | `PrintManager` + `NativePrintAdapter` (rejoue les commandes de dessin sur `PdfDocument.Page`) | — |
| `translate:langue` | ML Kit Translate (sur l'appareil, aucune clé) | texte traduit |

## Tout ceci est du vrai code natif, pas une Web API médiée par une WebView

Chaque ligne du tableau ci-dessus est un appel direct à une API Android (`android.hardware`, `android.media`, `androidx.biometric`, `com.google.android.gms.location`, ...) depuis Kotlin — il n'y a plus de WebView dans le chemin d'exécution du tout. C'est la différence structurelle avec l'ancienne génération (`Engine\Device\`, supprimée) qui passait par `assets/js/device.js`/`window.AndroidNative` : ce pont JS-vers-natif existe encore pour d'éventuelles pages WebView futures, mais plus aucun écran de l'app ne l'utilise.

## Ouvrir des liens externes / tel / mailto

Pas de service dédié actuellement (`Engine\Launcher\` a été supprimé faute de consommateur) — à réintroduire si besoin, sur le même modèle `device:` que le tableau ci-dessus : un `Intent.ACTION_VIEW` déclenché depuis `NativeRenderPocActivity.handleDeviceAction()`.

## Stockage persistant (`Engine\Preferences\`)

Équivalent `shared_preferences` — clé-valeur persistant, backé par `Engine\Database\Database::connection()` :

```php
Preferences::set('accent_color', 'purple');
Preferences::get('accent_color', 'blue');   // survit à un redémarrage de l'app
Preferences::has('accent_color');
Preferences::remove('accent_color');
```

Contrairement à `$_SESSION` (lié à la session HTTP), une valeur `Preferences` persiste après un redémarrage complet de l'app — c'est ce que `NativeSettingsScreen` utilise pour la couleur d'accent et le flag de rendu natif.

## Connectivité réseau

```php
$online = deviceBridge.isOnline()  // Kotlin, ConnectivityManager réel
```

`NativeDeviceBridge.kt::isOnline()` est appelé à CHAQUE requête `/native/layout-demo` (`&online=1|0` dans l'URL) — un écran lit `$_GET['online']` pour afficher un badge en/hors-ligne à jour sans polling JS. Voir `NativeSettingsScreen.php` pour l'exemple.

## Impression

`device:printpdf` — voir `NativePrintAdapter.kt` : réutilise `NativeCanvasView.drawForPrint()` (les mêmes fonctions `draw*Command` que l'écran) pour peindre directement sur les pages d'un `PdfDocument`, aucun document HTML/WebView à générer.

## Ce qui n'existe pas encore

- **Dialogue à formulaire** — `AlertButton`/`ConfirmButton` (voir [docs/widgets.md](widgets.md)) couvrent alert/confirm ; pas encore de dialogue générique portant son propre formulaire. ~~Bottom sheet natif~~ — existe réellement : `BottomSheet` (`packages/ui/src/Native/BottomSheet.php`) est un panneau modal ancré en bas, scrim tap-outside-to-dismiss, ouverture/fermeture animées (glissement), et une vraie poignée de drag continu pour le fermer à la main — voir la section "Le mécanisme générique derrière ça" de [docs/architecture.md](architecture.md).

~~Rapport d'erreurs~~ / ~~Deep linking~~ — les deux existent réellement côté natif, pas encore documentés ailleurs dans ce fichier : `CrashReporter.kt` (`android/engine`) est installé au démarrage (`MainActivity`/`NativeRenderPocActivity`), journalise les erreurs PHP (`logPhpError()`) et permet de partager un rapport (`report_crash`) ; le schéma `phpnitro://` route déjà vers un `screen=` natif via `deepLinkScreenToken()` (voir la section Deep Links de [docs/architecture.md](architecture.md)).
