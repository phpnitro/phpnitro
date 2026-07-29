<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAlertButton;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeBanner;
use Engine\Native\NativeConfirmButton;
use Engine\Native\NativeScaffold;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * The native conversion of WidgetsDialogsPage.php — both dialogs are
 * genuinely real android.app.AlertDialogs now (see NativeAlertButton/
 * NativeConfirmButton), not a WebView hosting a JS confirm() shim. There's
 * no Flash/toast mechanism on this pipeline yet (see NativeBanner's
 * docblock), so the "confirmed" feedback is a plain inline NativeBanner
 * for this one render instead of a one-shot fading toast.
 */
final class NativeWidgetsDialogsScreen
{
    public static function build(float $screenWidth, float $screenHeight): RenderNode
    {
        $confirmed = ($_GET['action'] ?? null) === 'widgets_confirm_demo';

        $caption = static fn (string $text): RenderNode => new RenderPadding(
            EdgeInsets::only(top: Tokens::SPACE_LG, bottom: Tokens::SPACE_SM),
            new RenderText($text, Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex(), bold: true, letterSpacing: 0.04),
        );

        $body = new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    new NativeBanner($confirmed ? 'Confirmé ! (action reçue par le serveur)' : null, icon: 'check_circle', foreground: Tokens::success(), background: Tokens::successMuted()),
                    $caption('AlertButton — vrai AlertDialog Android'),
                    new NativeAlertButton('Ceci est une vraie boîte de dialogue Android (AlertDialog), pas un window.alert().', 'Afficher une alerte', 'Info'),
                    $caption("ConfirmButton — n'appelle le serveur QUE si tu confirmes"),
                    new NativeConfirmButton('Confirmer cette action de démo ?', 'widgets_confirm_demo', 'Demander confirmation'),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new NativeScaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new NativeAppBar($screenWidth, 'Boîtes de dialogue', backAction: 'back'),
        );
    }
}
