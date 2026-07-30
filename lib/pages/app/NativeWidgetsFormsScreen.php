<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAlertButton;
use Engine\Native\NativeBanner;
use Engine\Native\NativeCheckbox;
use Engine\Native\NativeCircularProgress;
use Engine\Native\NativeConfirmButton;
use Engine\Native\NativeDatePicker;
use Engine\Native\NativeDivider;
use Engine\Native\NativeIconCircle;
use Engine\Native\NativeProgressBar;
use Engine\Native\NativeSelectBox;
use Engine\Native\NativeSwitch;
use Engine\Native\NativeTable;
use Engine\Native\NativeTextField;
use Engine\Native\NativeTimePicker;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderIcon;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\RenderWrap;
use Engine\Native\Tokens;

/**
 * The native conversion of WidgetsFormsPage.php — full parity. SelectBox
 * and DatePicker/TimePicker both open a real Android dialog (AlertDialog /
 * DatePickerDialog/TimePickerDialog) rather than anything drawn on the
 * Canvas itself; the dialog buttons (NativeAlertButton/NativeConfirmButton)
 * show the same native-dialog pattern for Engine\Dialogs\'s two widgets.
 * IconButton needs no separate demo here — NativeIconCircle already
 * covers it throughout the app (every screen's back button is one).
 */
final class NativeWidgetsFormsScreen
{
    public static function build(float $screenWidth): RenderNode
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;
        $selected = $_GET['country'] ?? '';
        $date = $_GET['appointment'] ?? '';
        $time = $_GET['meeting_time'] ?? '';
        $subscribed = ($_GET['subscribe'] ?? '') === '1';
        $notifications = ($_GET['notifications'] ?? '') === '1';
        $note = $_GET['note'] ?? '';

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

                    $caption('TimePicker — TimePickerDialog'),
                    new NativeTimePicker('meeting_time', $time),

                    $caption('Checkbox / Switch'),
                    new NativeCheckbox('subscribe', "S'abonner à la newsletter", $subscribed),
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_MD), new NativeSwitch('notifications', 'Notifications', $notifications)),

                    $caption('ProgressBar / CircularProgress'),
                    new NativeProgressBar($contentWidth, 0.65),
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_MD), new NativeCircularProgress(0.42)),

                    $caption('Banner (ErrorBanner)'),
                    new NativeBanner('Ceci est un message de validation.'),

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

                    $caption('Textarea — EditText multiligne réel'),
                    new NativeTextField('note', $note, 'Un commentaire...', multiline: true),

                    $caption('Icon — jeu étendu (2235 glyphes disponibles, voir MaterialIcons.php)'),
                    new RenderWrap(array_map(
                        static fn (string $name): RenderNode => new RenderIcon($name, 22.0, Tokens::inkSecondary()->toHex()),
                        ['check', 'close', 'search', 'favorite', 'star', 'delete', 'edit', 'download', 'upload', 'share', 'event', 'schedule', 'mail', 'phone', 'lock', 'notifications', 'info', 'visibility'],
                    ), spacing: Tokens::SPACE_MD, runSpacing: Tokens::SPACE_MD),

                    new NativeDivider(),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );
    }
}
