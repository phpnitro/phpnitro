<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeButton;
use Engine\Native\NativeIconCircle;
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
 * DevicePage.php has ~30 capabilities; this covers the ones that don't
 * need a UI overlay beyond a single synchronous call (camera preview,
 * image picker results, and similar stay on the WebView path — see
 * NativeDeviceBridge.kt's docblock). printPage() is the one exception
 * that looked portable but isn't: it needs WebView.createPrintDocumentAdapter,
 * so there's no document source without a WebView — it stays WebView-only.
 */
final class NativeDeviceScreen
{
    public static function build(float $screenWidth): RenderNode
    {
        $batteryOut = $_GET['battery_out'] ?? null;
        $deviceIdOut = $_GET['device_id_out'] ?? null;
        $bluetoothOut = $_GET['bt_out'] ?? null;
        $secureOut = $_GET['secure_out'] ?? null;
        $contactsOut = $_GET['contacts_out'] ?? null;
        $calendarOut = $_GET['calendar_out'] ?? null;

        $row = static fn (string $label, string $action, ?string $result = null): RenderNode => new RenderPadding(
            EdgeInsets::only(top: Tokens::SPACE_MD),
            RenderFlex::row([
                new NativeButton($label, $action, width: $result !== null ? null : $screenWidth - 2 * Tokens::SPACE_XL),
                ...($result !== null ? [new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_MD), new RenderText($result, Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true))] : []),
            ]),
        );

        return new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    new NativeIconCircle('arrow_back', action: 'back'),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new RenderText('Capacités du device', Tokens::TEXT_DISPLAY - 2, Tokens::ink()->toHex(), bold: true),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: 4),
                        new RenderText('Pont natif réel — NativeDeviceBridge.kt', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    ),
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
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );
    }
}
