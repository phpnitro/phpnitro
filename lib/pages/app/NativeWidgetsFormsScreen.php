<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAlertButton;
use Engine\Native\NativeConfirmButton;
use Engine\Native\NativeDatePicker;
use Engine\Native\NativeDivider;
use Engine\Native\NativeIconCircle;
use Engine\Native\NativeProgressBar;
use Engine\Native\NativeSelectBox;
use Engine\Native\NativeTable;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * The interactive-overlay half of WidgetsFormsPage.php's demo — SelectBox
 * and DatePicker both open a real Android dialog (AlertDialog /
 * DatePickerDialog) rather than anything drawn on the Canvas itself; the
 * dialog buttons (NativeAlertButton/NativeConfirmButton) show the same
 * native-dialog pattern for Engine\Dialogs\'s two widgets. ProgressBar and
 * Table are static, so they're demonstrated here too rather than
 * splitting one screen into two for no reason.
 */
final class NativeWidgetsFormsScreen
{
    public static function build(float $screenWidth): RenderNode
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;
        $selected = $_GET['country'] ?? '';
        $date = $_GET['appointment'] ?? '';

        $caption = static fn (string $text): RenderNode => new RenderPadding(
            EdgeInsets::only(top: Tokens::SPACE_LG, bottom: Tokens::SPACE_SM),
            new RenderText($text, Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex(), bold: true, letterSpacing: 0.04),
        );

        return new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    new NativeIconCircle('arrow_back', action: 'back'),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new RenderText('Formulaires', Tokens::TEXT_DISPLAY - 2, Tokens::ink()->toHex(), bold: true),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: 4),
                        new RenderText('SelectBox et DatePicker ouvrent un vrai dialogue Android.', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    ),

                    $caption('SelectBox — AlertDialog.setItems()'),
                    new NativeSelectBox('country', [
                        'fr' => 'France',
                        'be' => 'Belgique',
                        'ci' => "Côte d'Ivoire",
                        'sn' => 'Sénégal',
                    ], $selected),

                    $caption('DatePicker — DatePickerDialog'),
                    new NativeDatePicker('appointment', $date),

                    $caption('ProgressBar'),
                    new NativeProgressBar($contentWidth, 0.65),

                    $caption('Dialogues (Engine\\Dialogs\\)'),
                    RenderFlex::row([
                        new NativeAlertButton('Ceci est une alerte native.', 'Alerte'),
                        new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_MD), new NativeConfirmButton('Vraiment supprimer ?', 'widgets_confirm_delete', 'Supprimer')),
                    ]),

                    $caption('Table'),
                    new NativeTable(
                        rows: [['Casque sans fil', '89,90 €'], ['Montre connectée', '149,00 €']],
                        headers: ['Produit', 'Prix'],
                    ),
                    new NativeDivider(),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );
    }
}
