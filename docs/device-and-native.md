# Capacités device & natif

Chaque capacité passe par `device:<token>` comme `$action` d'un widget natif (`Button`, `Tappable`...) — `NativeRenderPocActivity.handleDeviceAction()` (Kotlin) reconnaît le token et appelle `NativeDeviceBridge.kt` **directement**, zéro requête HTTP tant que le résultat n'a pas besoin d'être affiché. Un résultat qui doit apparaître à l'écran est écrit dans `fieldValues[name]` côté client puis renvoyé à PHP au prochain re-fetch (`refetch(action = null, includeFields = true)`) — le même mécanisme qu'une valeur de champ de formulaire.

**N'écris jamais ces tokens à la main.** Chaque capacité ci-dessous (sauf les trois marquées "pas encore de classe" en bas de tableau) a une vraie classe `Engine\Device\*` — un *action-builder* qui construit le token pour toi et échappe correctement ses paramètres, plus un *result-reader* (`::result()`) qui lit `$_GET` — pas un widget imposé (voir la section "Le mécanisme générique" de [docs/architecture.md](architecture.md)) :

```php
use Engine\Device\Battery;
use Engine\Device\Notify;

new Button('Vibrer', Vibrate::vibrateAction());
new Button('Batterie', Battery::readAction('battery_out'), width: ...);
// Battery::result('battery_out') au prochain rendu contient le résultat

new Button('Notifier', Notify::showAction('Titre', 'Message'));
```

Voir `lib/pages/NativeDeviceScreen.php` pour l'exemple complet (toutes les capacités ci-dessous, une ligne chacune).

| Capacité | Classe `Engine\Device\` | Appelle (Kotlin) | Résultat |
|---|---|---|---|
| Vibrer | `Vibrate::vibrateAction()` | `Vibrator` | — |
| Torche | `Torch::toggleAction()` / `::result()` | `CameraManager` (flash) | `on`/`off` |
| Batterie | `Battery::readAction()` / `::result()` | `BatteryManager` | `%` |
| ID device | `DeviceId::readAction()` / `::result()` | `Settings.Secure.ANDROID_ID` | id |
| Bluetooth | `Bluetooth::stateAction()` / `::result()` | `BluetoothManager` | `on`/`off`/`unsupported` |
| Stockage sécurisé | `SecureStorage::storeAction()`/`retrieveAction()` / `::result()` | Keystore-backed `EncryptedSharedPreferences` | valeur déchiffrée |
| Contacts | `Contacts::countAction()` / `::result()` | `ContentResolver` (contacts) | nombre |
| Calendrier | `CalendarEvents::countAction()` / `::result()` | `ContentResolver` (calendrier) | nombre d'événements à venir |
| Son | `Sound::playAction($url)` | `MediaPlayer` (effet court) | — |
| Notification | `Notify::showAction($titre, $message)` | `NotificationCompat` | — |
| Partager | `Share::shareAction($texte, $titre)` | `Intent.ACTION_SEND` + `createChooser` | — |
| Icône dynamique | `DynamicIcon::setAction($clé)` | deux `activity-alias` (ou plus) mutuellement exclusifs | — |
| Photo | `Camera::captureAction()` / `::result()` | `ActivityResultContracts.TakePicturePreview` | photo |
| Choisir une image | `ImagePicker::pickAction()` / `::result()` | `ActivityResultContracts.GetContent` | image choisie |
| Biométrie | `Fingerprint::authenticateAction()` / `::result()` | `BiometricPrompt` (androidx.biometric) | succès/échec |
| Luminosité | `Brightness::setAction($niveau)` | `WindowManager.LayoutParams.screenBrightness` | — |
| Micro | `VoiceRecorder::recordAction()` / `::result()` | `MediaRecorder` (clip court) | statut |
| Accéléromètre | `Sensors::readAccelerometerAction()` / `::result()` | `SensorEventListener` (lecture unique) | valeur |
| NFC | `Nfc::startListeningAction()`/`stopListeningAction()` / `::result()` | `NfcAdapter` foreground dispatch | tag lu |
| Achat intégré | `InAppPurchase::queryAction()`/`purchaseAction()` / `::result()` | `BillingClient` (Play Billing) | infos produit |
| Géofencing | `Geofence::addAction()`/`removeAction()` | `GeofencingClient` | — |
| Tâche de fond | `BackgroundTask::scheduleAction()`/`cancelAction()` | `WorkManager` | — |
| Alarme planifiée | `AlarmScheduler::scheduleAction()` | `AlarmManager` + `AlarmReceiver` (survit même app tuée) | — |
| Impression | *(pas encore de classe — token `device:printpdf` direct)* | `PrintManager` + `NativePrintAdapter` (rejoue les commandes de dessin sur `PdfDocument.Page`) | — |
| Géolocalisation | *(pas encore de classe — token `device:locate:champ` direct)* | `FusedLocationProviderClient` | lat/lon |
| Traduction | *(pas encore de classe — token `device:translate:langue` direct)* | ML Kit Translate (sur l'appareil, aucune clé) | texte traduit |

Un même mécanisme couvre aussi des capacités hors de `packages/ui/src/Device/` mais suivant le même token `device:` : `Connectivity` (réseau, en dessous), `Permission`/`QrScanner`/`AppLinks`/`AppSettings`/`OpenFile`/`InAppUpdate`/`FileSelector`/`MapLauncher`/`FileSaver`/`Clipboard`/`EmailSender`/`RestartApp`/`WebSocket`/`ImageCropper` — toutes déjà des classes `Engine\Device\*`, voir `lib/pages/NativeDeviceScreen.php` pour chacune en contexte.

**Historique honnête** : ~20 de ces classes (Battery, Bluetooth, SecureStorage, Contacts, CalendarEvents, Sound, Notify, Share, Fingerprint, Brightness, VoiceRecorder (alors nommée Microphone), Sensors, Nfc, InAppPurchase, Geofence, BackgroundTask, ImagePicker, Torch, Vibrate) ont été supprimées par erreur dans un commit de juillet sans rapport (`d910455`), jamais restaurées jusqu'à ce qu'un signalement direct ("ça sera grave pour les dev d'utiliser") déclenche leur reconstruction complète — le natif Kotlin, lui, n'avait jamais bougé. `AlarmScheduler` est la seule vraiment neuve : son ancien code appelait le pont JS de l'ère WebView (supprimé), remplacée par une intégration directe de l'`AlarmReceiver` déjà partagé avec ce chemin.

## Tout ceci est du vrai code natif, pas une Web API médiée par une WebView

Chaque ligne du tableau ci-dessus est un appel direct à une API Android (`android.hardware`, `android.media`, `androidx.biometric`, `com.google.android.gms.location`, ...) depuis Kotlin — il n'y a plus de WebView dans le chemin d'exécution du tout.

## Ouvrir des liens externes / tel / mailto

`Engine\Device\UrlLauncher::openAction($url)` — ouvre un `Intent.ACTION_VIEW` depuis `NativeRenderPocActivity.handleDeviceAction()`, fonctionne aussi pour `tel:`/`mailto:` (n'importe quel schéma qu'une app installée sait résoudre). Pour ouvrir un point précis dans une app de cartes plutôt qu'une URL générique, voir `MapLauncher::openAction($lat, $lon, $label)` à la place.

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
use Engine\Device\Connectivity;

new Button('Connectivité', Connectivity::checkAction(), ...);
// Connectivity::result() -> 'online' | 'offline'
```

`NativeDeviceBridge.kt::isOnline()` est aussi appelé à CHAQUE requête `/native/layout-demo` (`&online=1|0` dans l'URL) — un écran peut lire `$_GET['online']` directement pour afficher un badge en/hors-ligne à jour sans même passer par `Connectivity::checkAction()`. Voir `NativeSettingsScreen.php` pour l'exemple.

## Impression

`device:printpdf` — voir `NativePrintAdapter.kt` : réutilise `NativeCanvasView.drawForPrint()` (les mêmes fonctions `draw*Command` que l'écran) pour peindre directement sur les pages d'un `PdfDocument`, aucun document HTML/WebView à générer.

## Ce qui n'existe pas encore

- **Dialogue à formulaire** — `AlertButton`/`ConfirmButton` (voir [docs/widgets.md](widgets.md)) couvrent alert/confirm ; pas encore de dialogue générique portant son propre formulaire.
- **Classes PHP pour géolocalisation/impression/traduction** — le natif Kotlin fonctionne (voir tableau ci-dessus), juste pas encore de `Engine\Device\Geolocation`/`Printing`/`Translator`-style wrapper ; token brut à utiliser en attendant.

~~Bottom sheet natif~~ — existe réellement : `BottomSheet` (`packages/ui/src/Native/BottomSheet.php`) est un panneau modal ancré en bas, scrim tap-outside-to-dismiss, ouverture/fermeture animées (glissement), et une vraie poignée de drag continu pour le fermer à la main — voir la section "Le mécanisme générique derrière ça" de [docs/architecture.md](architecture.md).

~~Rapport d'erreurs~~ / ~~Deep linking~~ — les deux existent réellement côté natif : `CrashReporter.kt` (`android/engine`) est installé au démarrage (`MainActivity`/`NativeRenderPocActivity`), journalise les erreurs PHP (`logPhpError()`) et permet de partager un rapport (`report_crash`) ; le schéma `phpnitro://` route déjà vers un `screen=` natif via `deepLinkScreenToken()` (voir la section Deep Links de [docs/architecture.md](architecture.md)).
