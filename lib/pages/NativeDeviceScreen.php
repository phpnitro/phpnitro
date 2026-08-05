<?php

namespace Engine\App;

use Engine\Native\AudioRecorder;
use Engine\Native\CameraButton;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\AppBar;
use Engine\Native\Button;
use Engine\Native\Divider;
use Engine\Native\Scaffold;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
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
        $batteryOut = $_GET['battery_out'] ?? null;
        $deviceIdOut = $_GET['device_id_out'] ?? null;
        $bluetoothOut = $_GET['bt_out'] ?? null;
        $secureOut = $_GET['secure_out'] ?? null;
        $contactsOut = $_GET['contacts_out'] ?? null;
        $calendarOut = $_GET['calendar_out'] ?? null;
        $photoOut = $_GET['photo_out'] ?? null;
        $pickedImageOut = $_GET['picked_image_out'] ?? null;
        $biometricOut = $_GET['biometric_out'] ?? null;
        $locationOut = $_GET['location_out'] ?? null;
        $micOut = $_GET['mic_out'] ?? null;
        $sensorOut = $_GET['sensor_out'] ?? null;
        $nfcOut = $_GET['nfc_out'] ?? null;
        $iapOut = $_GET['iap_out'] ?? null;

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
                    $row('Vibrer', 'device:vibrate'),
                    $row('Torche', 'device:torch'),
                    $row('Batterie', 'device:battery:battery_out', $batteryOut),
                    $row('ID device', 'device:deviceid:device_id_out', $deviceIdOut),
                    $row('Bluetooth', 'device:bluetooth:bt_out', $bluetoothOut),
                    $row('Stocker un secret', 'device:securestore:demo_key'),
                    $row('Lire le secret', 'device:secureretrieve:demo_key:secure_out', $secureOut),
                    $row('Contacts', 'device:contacts:contacts_out', $contactsOut),
                    $row('Calendrier', 'device:calendar:calendar_out', $calendarOut),
                    $row('Jouer un son', 'device:sound'),
                    $row('Notification', 'device:notify'),
                    $row('Partager', 'device:share'),
                    $row('Icône bleue', 'device:appicon:alt'),
                    $row('Icône par défaut', 'device:appicon:default'),
                    $row('Photo native', 'device:camera', $photoOut),
                    $row('Choisir une image', 'device:pickimage', $pickedImageOut),
                    $row('Authentifier (biométrie)', 'device:biometric:biometric_out', $biometricOut),
                    $row('Luminosité 50%', 'device:brightness'),
                    $row('Localiser', 'device:locate:location_out', $locationOut),
                    $row('Activer le micro (2s)', 'device:mic:mic_out', $micOut),
                    new Text('Widgets CameraButton/AudioRecorder (mêmes actions, prêts à l\'emploi) :', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new CameraButton()),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new AudioRecorder(outputField: 'clip_out', durationMs: 3000)),
                    ...(($_GET['clip_out'] ?? null) !== null
                        ? [new Padding(EdgeInsets::only(top: Tokens::SPACE_SM), new Text($_GET['clip_out'], Tokens::TEXT_BODY, Tokens::ink()->toHex()))]
                        : []),
                    $row('Accéléromètre', 'device:sensor:sensor_out', $sensorOut),
                    $row('Écouter NFC', 'device:nfcstart'),
                    $row('Arrêter NFC', 'device:nfcstop', $nfcOut),
                    $row('Produits (achat intégré)', 'device:iapquery:iap_out', $iapOut),
                    $row('Acheter demo_product', 'device:iappurchase'),
                    $row('Activer zone (Paris, 200m)', 'device:geofenceadd'),
                    $row('Désactiver la zone', 'device:geofenceremove'),
                    $row('Planifier tâche de fond', 'device:bgschedule'),
                    $row('Annuler tâche de fond', 'device:bgcancel'),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_LG), new Divider()),
                    $row('Imprimer (PDF)', 'device:printpdf'),
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
