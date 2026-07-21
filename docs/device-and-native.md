# Capacités device & natif

`Engine\Device\` (`packages/device/src/`) — des **services**, pas des widgets : chaque classe expose une méthode statique qui retourne soit une expression JS à attacher via `Button::make($label, onClick: ...)` à N'IMPORTE QUEL bouton, soit un élément de sortie (`Widget`) à placer où tu veux. Voir `lib/pages/app/DevicePage.php` (route `/device`) pour un exemple complet.

| Service | Méthode(s) |
|---|---|
| `Device\Vibrate` | `::onClick($milliseconds = 200): string` |
| `Device\Notify` | `::onClick($title, $message): string` |
| `Device\Sound` | `::onClick($url): string` |
| `Device\Printer` | `::onClick(): string` |
| `Device\Microphone` | `::onClick($outputId): string` + `::outputElement($id): Widget` |
| `Device\Fingerprint` | `::onClick($outputId): string` + `::outputElement($id): Widget` |
| `Device\Camera` | `::openOnClick($videoId)` + `::captureOnClick($imageId)` + `::videoElement($id)` + `::imageElement($id)` |
| `Device\ImagePicker` | `::pickOnClick($previewId, $hiddenFieldId)` + `::hiddenField($name, $id)` + `::previewElement($id)` |
| `Device\Share` | `::onClick($text, $title = ''): string` — vrai share sheet natif |
| `Device\AlarmScheduler` | `::onClick($requestCode, $delaySeconds, $title, $message): string` — notification programmée, survit à la fermeture de l'app |
| `Device\AppIcon` | `::onClick($iconKey): string` — change l'icône du lanceur à l'exécution |

Exemple :

```php
Button::make('Faire vibrer mon bouton perso', onClick: Vibrate::onClick(300), classes: 'bg-purple-600 text-white ...')
```

Tous passent par `assets/js/device.js` (`window.phpxDevice`), qui **préfère toujours le pont natif** (`window.AndroidNative`/`window.iOSNative`) et ne retombe sur les Web APIs standard que si ce pont est absent.

## Ce qui passe par du vrai code natif (pas une Web API médiée par la WebView)

- **Vibreur** — `Vibrator` directement.
- **Caméra** — lance la vraie app Camera du système.
- **Biométrie** — `BiometricPrompt` (androidx.biometric). WebView n'implémente PAS WebAuthn/FIDO2 — le pont natif est la seule voie fiable.
- **Notifications** — `NotificationCompat`, fonctionne hors-ligne.
- **Son** — `MediaPlayer`, continue à travers le verrouillage écran.
- **Impression / PDF** — le vrai pipeline système (`WebView.createPrintDocumentAdapter` + `PrintManager`).
- **Sélecteur d'image** — la vraie app galerie/fichiers du système.
- **Partage** — `Intent.ACTION_SEND` + `createChooser` (le vrai share sheet).
- **Alarme planifiée** — `AlarmManager` + `BroadcastReceiver` dédié (`AlarmReceiver.kt`), la notification s'affiche même si l'app a été tuée entre-temps.
- **Icône d'app dynamique** — deux `activity-alias` Android mutuellement exclusifs, basculés via `PackageManager.setComponentEnabledSetting`.

**Ce qui reste médié par la WebView (mais réel, pas simulé)** : géolocalisation et micro délèguent aux vraies APIs Android en coulisses via Chromium.

## Ouvrir des liens externes / tel / mailto (`Engine\Launcher\`)

Une simple balise `<a href="tel:...">` échoue silencieusement dans une WebView sans traitement particulier. `Launcher` déclenche un vrai `Intent.ACTION_VIEW` natif :

```php
Button::make('Appeler', onClick: Launcher::call('+229...'))
Button::make('Écrire un e-mail', onClick: Launcher::email('contact@example.com', 'Sujet'))
Button::make('Ouvrir le site', onClick: Launcher::openUrl('https://example.com'))
Launcher::sms($phoneNumber, $body = '')
```

`MainActivity.kt` intercepte aussi directement les liens `tel:`/`mailto:`/`sms:` cliqués dans une page (`shouldOverrideUrlLoading`), pas seulement via ce service.

## Deep linking

Schéma personnalisé `phpnitro://<chemin>` (ex. `phpnitro://product/42` → route `/product/42`, résolue par le même `Engine\Router` qu'une navigation normale). `android:launchMode="singleTask"` : un deep link tapé pendant que l'app tourne réutilise l'instance existante au lieu d'en empiler une seconde.

Pas encore de vrais App Links HTTPS (`android:autoVerify` + `.well-known/assetlinks.json` sur un domaine vérifié) — le schéma personnalisé n'est pas cliquable depuis un lien web/SMS/email comme le serait un vrai App Link.

## Boîtes de dialogue (`Engine\Dialogs\`)

`AlertButton`/`ConfirmButton` — natives d'abord : `assets/js/dialogs.js` appelle une vraie `AlertDialog` Android, et ne retombe sur `window.alert()`/`window.confirm()` du navigateur que si le pont natif est absent. `ConfirmButton` n'appelle l'action serveur que depuis le callback de confirmation.

## Gestes

`GestureDetector` est le seul endroit du framework qui utilise du JavaScript côté client par nécessité (`assets/js/gestures.js`) — un double-clic/swipe ne peut pas être détecté en HTTP pur. Le JS ne fait que déclencher la même mécanique d'action que les boutons.

## Rapport d'erreurs (`Engine\Diagnostics\CrashReporter`)

Équivalent Crashlytics/Sentry, auto-hébergé (aucun compte tiers requis) :

```php
CrashReporter::install('https://ton-serveur.example.com/api/crash-reports');
```

Capture les exceptions/erreurs PHP côté serveur et les erreurs JS/promesses rejetées côté client (`assets/js/diagnostics.js`, actif si `window.phpxCrashReportUrl` est défini), POST le tout vers l'endpoint fourni.

## Stockage persistant (`Engine\Preferences\`)

Équivalent `shared_preferences` — clé-valeur persistant, backé par `Engine\Database\Database::connection()` (donc SQLite par défaut, ou le même `DATABASE_URL` que le reste de l'app) :

```php
Preferences::set('accent_color', 'purple');
Preferences::get('accent_color', 'blue');   // survit à un redémarrage de l'app
Preferences::has('accent_color');
Preferences::remove('accent_color');
```

Contrairement à `$this->state` d'un `Screen` (lié à la session), une valeur `Preferences` persiste après un redémarrage complet de l'app.

## Connectivité réseau (`Engine\Connectivity\`)

```php
ConnectivityBadge::make(onlineLabel: 'En ligne', offlineLabel: 'Hors ligne')
```

Rendu placeholder côté serveur, peint avec l'état réel au montage et à chaque changement `online`/`offline` par `assets/js/connectivity.js`. Le type de connexion (wifi/cellulaire/aucune) passe par `window.AndroidNative.getConnectionType()` (`ConnectivityManager` natif) quand disponible.

## Accessibilité

Voir [docs/widgets.md#accessibilité](widgets.md#accessibilité).
