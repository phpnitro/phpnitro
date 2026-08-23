<?php

namespace Engine\App;

use Engine\Device\AppLinks;
use Engine\Device\AppSettings;
use Engine\Device\BackgroundTask;
use Engine\Device\Battery;
use Engine\Device\Bluetooth;
use Engine\Device\Brightness;
use Engine\Device\CalendarEvents;
use Engine\Device\Camera;
use Engine\Device\Clipboard;
use Engine\Device\Connectivity;
use Engine\Device\Contacts;
use Engine\Device\DeviceId;
use Engine\Device\EmailSender;
use Engine\Device\FileSaver;
use Engine\Device\FileSelector;
use Engine\Device\Fingerprint;
use Engine\Device\DynamicIcon;
use Engine\Device\Geofence;
use Engine\Device\ImageCropper;
use Engine\Device\ImagePicker;
use Engine\Device\InAppPurchase;
use Engine\Device\InAppReview;
use Engine\Device\InAppUpdate;
use Engine\Device\MapLauncher;
use Engine\Device\Nfc;
use Engine\Device\Notify;
use Engine\Device\OpenFile;
use Engine\Device\RestartApp;
use Engine\Device\SecureStorage;
use Engine\Device\Sensors;
use Engine\Device\Share;
use Engine\Device\Sound;
use Engine\Device\Torch;
use Engine\Device\UrlLauncher;
use Engine\Device\Vibrate;
use Engine\Device\WebSocket;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\AppBar;
use Engine\Native\Button;
use Engine\Native\Divider;
use Engine\Device\VoiceRecorder;
use Engine\Native\Scaffold;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Device\Permission;
use Engine\Device\QrScanner;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * The native conversion of a first slice of DevicePage.php — real device
 * capabilities via NativeDeviceBridge.kt, not a server round-trip
 * pretending to be one: "device:vibrate"/"device:torch" call straight
 * into Android APIs from NativeRenderPocActivity with no PHP involved at
 * all. The rest (battery/deviceid/bluetooth/secure-retrieve) do involve
 * PHP, but only to receive a value that already came from a real
 * BatteryManager/Settings.Secure/BluetoothAdapter/Keystore call and
 * display it — same "$_GET['x_out'] carries a result" mechanism
 * TextField uses for typed input, just in the other direction.
 * Secure storage is genuinely shared with the WebView path (same
 * Keystore-backed file, see NativeDeviceBridge.kt) — a secret stored via
 * one rendering path is readable from the other.
 *
 * DevicePage.php has ~30 capabilities; camera capture, image picking,
 * biometric auth, brightness, geolocation, mic recording, sensors, NFC
 * foreground dispatch, geofencing, in-app purchase and periodic
 * background tasks are all real native calls now too (see
 * NativeDeviceBridge.kt / NativeRenderPocActivity.kt's NFC lifecycle
 * hooks). Printing is real too now — android.print.PrintManager +
 * NativePrintAdapter, which replays this screen's own draw commands onto
 * a PdfDocument.Page's Canvas (NativeCanvasView.drawForPrint()) instead of
 * WebView.createPrintDocumentAdapter(). Full parity reached — DevicePage.php
 * (the WebView version) has been removed.
 */
final class NativeDeviceScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        $torchOut = Torch::result();
        $batteryOut = Battery::result();
        $deviceIdOut = DeviceId::result();
        $bluetoothOut = Bluetooth::result();
        $secureOut = SecureStorage::result();
        $contactsOut = Contacts::result();
        $calendarOut = CalendarEvents::result();
        $photoOut = Camera::result();
        $pickedImageOut = ImagePicker::result();
        $biometricOut = Fingerprint::result();
        $locationOut = $_GET['location_out'] ?? null;
        $micOut = VoiceRecorder::result();
        $sensorOut = Sensors::result();
        $nfcOut = Nfc::result();
        $iapOut = InAppPurchase::result();
        $locPermOut = Permission::result('loc_perm_out');
        $qrOut = QrScanner::result();
        $connectivityOut = Connectivity::result();
        $appLinkOut = AppLinks::result();
        $updateOut = InAppUpdate::result();
        $fileOut = FileSelector::result();
        $saveOut = FileSaver::result();
        $clipboardOut = Clipboard::result();
        $wsOut = WebSocket::result();
        $cropOut = ImageCropper::result();

        $row = static fn (string $label, string $action, ?string $result = null): Widget => new Padding(
            EdgeInsets::only(top: Tokens::SPACE_MD),
            Flex::row([
                new Button($label, $action, width: $result !== null ? null : $screenWidth - 2 * Tokens::SPACE_XL),
                ...($result !== null ? [new Padding(EdgeInsets::only(left: Tokens::SPACE_MD), new Text($result, Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true))] : []),
            ]),
        );

        $body = new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    new Text('Pont natif réel — NativeDeviceBridge.kt', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    $row('Vibrer', Vibrate::vibrateAction()),
                    $row('Torche', Torch::toggleAction(), $torchOut),
                    $row('Batterie', Battery::readAction(), $batteryOut),
                    $row('ID device', DeviceId::readAction(), $deviceIdOut),
                    $row('Bluetooth', Bluetooth::stateAction(), $bluetoothOut),
                    $row('Stocker un secret', SecureStorage::storeAction('demo_key', 'valeur secrète')),
                    $row('Lire le secret', SecureStorage::retrieveAction('demo_key'), $secureOut),
                    $row('Contacts', Contacts::countAction(), $contactsOut),
                    $row('Calendrier', CalendarEvents::countAction(), $calendarOut),
                    $row('Jouer un son', Sound::playAction('http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1') . '/assets/audio/beep.wav')),
                    $row('Notification', Notify::showAction('PhpNitro', 'Ceci est une notification native.')),
                    $row('Partager', Share::shareAction('Regarde cette app faite avec PhpNitro !', 'PhpNitro Demo')),
                    $row('Icône bleue', DynamicIcon::setAction('alt')),
                    $row('Icône par défaut', DynamicIcon::setAction('default')),
                    $row('Photo native', Camera::captureAction(), $photoOut),
                    $row('Choisir une image', ImagePicker::pickAction(), $pickedImageOut),
                    $row('Authentifier (biométrie)', Fingerprint::authenticateAction(), $biometricOut),
                    $row('Luminosité 50%', Brightness::setAction(0.5)),
                    $row('Localiser', 'device:locate:location_out', $locationOut),
                    $row('Activer le micro (2s)', VoiceRecorder::recordAction(), $micOut),
                    $row('Vérifier/demander la localisation', Permission::requestAction('location', 'loc_perm_out'), $locPermOut),
                    $row('Scanner un QR code', QrScanner::scanAction(), $qrOut),
                    $row('Accéléromètre', Sensors::readAccelerometerAction(), $sensorOut),
                    $row('Écouter NFC', Nfc::startListeningAction()),
                    $row('Arrêter NFC', Nfc::stopListeningAction(), $nfcOut),
                    $row('Produits (achat intégré)', InAppPurchase::queryAction('demo_product'), $iapOut),
                    $row('Acheter demo_product', InAppPurchase::purchaseAction('demo_product')),
                    $row('Activer zone (Paris, 200m)', Geofence::addAction('paris_demo', 48.8566, 2.3522, 200)),
                    $row('Désactiver la zone', Geofence::removeAction('paris_demo')),
                    $row('Planifier tâche de fond', BackgroundTask::scheduleAction('/api/ping')),
                    $row('Annuler tâche de fond', BackgroundTask::cancelAction()),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_LG), new Divider()),
                    $row('Imprimer (PDF)', 'device:printpdf'),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_LG), new Divider()),
                    new Text('Capacités device — packages Engine\\Device (aucun widget imposé)', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    $row('Ouvrir un lien', UrlLauncher::openAction('https://phpnitro.dev')),
                    $row('Connectivité', Connectivity::checkAction(), $connectivityOut),
                    $row('Demander un avis', InAppReview::requestAction()),
                    $row('Dernier lien entrant', AppLinks::lastLinkAction(), $appLinkOut),
                    $row('Réglages de l\'app', AppSettings::openAction('app')),
                    $row('Ouvrir un fichier', OpenFile::openAction('Bonjour depuis PhpNitro !')),
                    $row('Vérifier les mises à jour', InAppUpdate::checkAction(), $updateOut),
                    $row('Choisir un fichier', FileSelector::pickAction(), $fileOut),
                    $row('Ouvrir Paris dans Maps', MapLauncher::openAction(48.8566, 2.3522, 'Paris')),
                    $row('Enregistrer une note', FileSaver::saveAction('note.txt', 'Ma note PhpNitro'), $saveOut),
                    $row('Copier dans le presse-papiers', Clipboard::copyAction('Copié depuis PhpNitro !')),
                    $row('Lire le presse-papiers', Clipboard::pasteAction(), $clipboardOut),
                    $row('Envoyer un email', EmailSender::composeAction('contact@example.com', 'Bonjour', 'Message envoyé depuis PhpNitro.')),
                    $row('Redémarrer l\'app', RestartApp::restartAction()),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_LG), new Divider()),
                    new Text('WebSocket — connexion persistante réelle (survit à l\'arrière-plan)', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    $row('Connecter (echo public)', WebSocket::connectAction('wss://ws.postman-echo.com/raw', 'ws_out')),
                    $row('Envoyer "Bonjour PhpNitro !"', WebSocket::sendAction('Bonjour PhpNitro !')),
                    $row('Déconnecter', WebSocket::disconnectAction()),
                    ...($wsOut !== null ? [new Padding(EdgeInsets::only(top: Tokens::SPACE_SM), new Text("Reçu : {$wsOut}", Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true))] : []),
                    $row('Recadrer une image', ImageCropper::cropAction(), $cropOut),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new Scaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new AppBar($screenWidth, 'Capacités du device', backAction: 'back'),
            bottomNav: NativeAppShell::bottomNav($screenWidth, 'device'),
        );
    }
}
