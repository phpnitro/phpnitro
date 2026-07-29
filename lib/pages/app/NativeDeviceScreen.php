<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeButton;
use Engine\Native\NativeDivider;
use Engine\Native\NativeScaffold;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
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
 * NativeTextField uses for typed input, just in the other direction.
 * Secure storage is genuinely shared with the WebView path (same
 * Keystore-backed file, see NativeDeviceBridge.kt) — a secret stored via
 * one rendering path is readable from the other.
 *
 * DevicePage.php has ~30 capabilities; camera capture, image picking,
 * biometric auth, brightness, geolocation and mic recording are all real
 * native calls now too (see NativeDeviceBridge.kt) — what's still missing
 * is NFC foreground dispatch, printing (needs a WebView document source),
 * geofencing and in-app purchase, each its own real chunk of lifecycle-
 * heavy Android API work, not yet ported.
 */
final class NativeDeviceScreen
{
    public static function build(float $screenWidth, float $screenHeight): RenderNode
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

        $row = static fn (string $label, string $action, ?string $result = null): RenderNode => new RenderPadding(
            EdgeInsets::only(top: Tokens::SPACE_MD),
            RenderFlex::row([
                new NativeButton($label, $action, width: $result !== null ? null : $screenWidth - 2 * Tokens::SPACE_XL),
                ...($result !== null ? [new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_MD), new RenderText($result, Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true))] : []),
            ]),
        );

        $body = new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    new RenderText('Pont natif réel — NativeDeviceBridge.kt', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
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
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_LG), new NativeDivider()),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new RenderText(
                            'NFC, impression, géofencing et achats in-app nécessitent encore une WebView.',
                            Tokens::TEXT_BODY_SMALL,
                            Tokens::inkMuted()->toHex(),
                        ),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeButton('Fonctionnalités avancées (WebView)', 'webview:/device', background: Tokens::surfaceMuted(), foreground: Tokens::ink()),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new NativeScaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new NativeAppBar($screenWidth, 'Capacités du device', backAction: 'back'),
            bottomNav: NativeAppShell::bottomNav($screenWidth, 'device'),
        );
    }
}
