<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\AlertButton;
use Engine\Native\AppBar;
use Engine\Native\Banner;
use Engine\Native\ConfirmButton;
use Engine\Native\Scaffold;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * The native conversion of WidgetsDialogsPage.php — both dialogs are
 * genuinely real android.app.AlertDialogs now (see AlertButton/
 * ConfirmButton), not a WebView hosting a JS confirm() shim. There's
 * no Flash/toast mechanism on this pipeline yet (see Banner's
 * docblock), so the "confirmed" feedback is a plain inline Banner
 * for this one render instead of a one-shot fading toast.
 */
final class NativeWidgetsDialogsScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        $confirmed = ($_GET['action'] ?? null) === 'widgets_confirm_demo';

        $caption = static fn (string $text): Widget => new Padding(
            EdgeInsets::only(top: Tokens::SPACE_LG, bottom: Tokens::SPACE_SM),
            new Text($text, Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex(), bold: true, letterSpacing: 0.04),
        );

        $body = new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    new Banner($confirmed ? 'Confirmé ! (action reçue par le serveur)' : null, icon: 'check_circle', foreground: Tokens::success(), background: Tokens::successMuted()),
                    $caption('AlertButton — vrai AlertDialog Android'),
                    new AlertButton('Ceci est une vraie boîte de dialogue Android (AlertDialog), pas un window.alert().', 'Afficher une alerte', 'Info'),
                    $caption("ConfirmButton — n'appelle le serveur QUE si tu confirmes"),
                    new ConfirmButton('Confirmer cette action de démo ?', 'widgets_confirm_demo', 'Demander confirmation'),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new Scaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new AppBar($screenWidth, 'Boîtes de dialogue', backAction: 'back'),
        );
    }
}
