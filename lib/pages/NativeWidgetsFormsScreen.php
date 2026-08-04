<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\AlertButton;
use Engine\Native\Badge;
use Engine\Native\Banner;
use Engine\Native\BottomSheet;
use Engine\Native\Center;
use Engine\Native\Checkbox;
use Engine\Native\Button;
use Engine\Native\Chip;
use Engine\Native\CircularProgress;
use Engine\Native\Confetti;
use Engine\Native\ConfirmButton;
use Engine\Native\DatePicker;
use Engine\Native\Divider;
use Engine\Native\IconCircle;
use Engine\Native\Positioned;
use Engine\Native\ProgressBar;
use Engine\Native\Skeleton;
use Engine\Native\Snackbar;
use Engine\Native\Stack;
use Engine\Native\RadioGroup;
use Engine\Native\SelectBox;
use Engine\Native\Slider;
use Engine\Native\Toggle;
use Engine\Native\Table;
use Engine\Native\TextField;
use Engine\Native\TimePicker;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Icon;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\RichText;
use Engine\Native\Text;
use Engine\Native\Wrap;
use Engine\Native\TextSpan;
use Engine\Native\Tokens;

/**
 * The native conversion of WidgetsFormsPage.php — full parity. SelectBox
 * and DatePicker/TimePicker both open a real Android dialog (AlertDialog /
 * DatePickerDialog/TimePickerDialog) rather than anything drawn on the
 * Canvas itself; the dialog buttons (AlertButton/ConfirmButton)
 * show the same native-dialog pattern for Engine\Dialogs\'s two widgets.
 * IconButton needs no separate demo here — IconCircle already
 * covers it throughout the app (every screen's back button is one).
 */
final class NativeWidgetsFormsScreen
{
    public static function build(float $screenWidth): Widget
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;
        $selected = $_GET['country'] ?? '';
        $date = $_GET['appointment'] ?? '';
        $time = $_GET['meeting_time'] ?? '';
        $subscribed = ($_GET['subscribe'] ?? '') === '1';
        $notifications = ($_GET['notifications'] ?? '') === '1';
        $note = $_GET['note'] ?? '';
        $volume = (float) ($_GET['volume'] ?? '0.5');
        $plan = $_GET['plan'] ?? 'free';

        $caption = static fn (string $text): Widget => new Padding(
            EdgeInsets::only(top: Tokens::SPACE_LG, bottom: Tokens::SPACE_SM),
            new Text($text, Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex(), bold: true, letterSpacing: 0.04),
        );

        return new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    new IconCircle('arrow_back', action: 'back'),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new Text('Formulaires', Tokens::TEXT_DISPLAY - 2, Tokens::ink()->toHex(), bold: true),
                    ),
                    new Padding(
                        EdgeInsets::only(top: 4),
                        new Text('SelectBox et DatePicker ouvrent un vrai dialogue Android.', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    ),

                    $caption('SelectBox — AlertDialog.setItems()'),
                    new SelectBox('country', [
                        'fr' => 'France',
                        'be' => 'Belgique',
                        'ci' => "Côte d'Ivoire",
                        'sn' => 'Sénégal',
                    ], $selected),

                    $caption('DatePicker — DatePickerDialog'),
                    new DatePicker('appointment', $date),

                    $caption('TimePicker — TimePickerDialog'),
                    new TimePicker('meeting_time', $time),

                    $caption('Checkbox / Switch'),
                    new Checkbox('subscribe', "S'abonner à la newsletter", $subscribed),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new Toggle('notifications', 'Notifications', $notifications)),

                    $caption('Slider — valeur continue, glisser le pouce'),
                    new Slider('volume', $volume, $contentWidth),
                    new Padding(EdgeInsets::only(top: 4), new Text(sprintf('Valeur : %.2f', $volume), Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex())),

                    $caption('RadioGroup — choix exclusif'),
                    new RadioGroup('plan', ['free' => 'Gratuit', 'pro' => 'Pro', 'team' => 'Équipe'], $plan),

                    $caption('ProgressBar / CircularProgress'),
                    new ProgressBar($contentWidth, 0.65),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new CircularProgress(0.42)),

                    $caption('Banner (ErrorBanner)'),
                    new Banner('Ceci est un message de validation.'),

                    $caption('Dialogues (Engine\\Dialogs\\)'),
                    Flex::row([
                        new AlertButton('Ceci est une alerte native.', 'Alerte'),
                        new Padding(EdgeInsets::only(left: Tokens::SPACE_MD), new ConfirmButton('Vraiment supprimer ?', 'widgets_confirm_delete', 'Supprimer')),
                    ]),

                    $caption('Confetti — burst manuel (voir aussi Paiements, déclenché auto sur succès)'),
                    new Button('🎉 Lancer les confettis', Confetti::triggerAction(), width: $contentWidth),

                    $caption('Snackbar — message transitoire (3s, auto-disparait)'),
                    new Button('Afficher un Snackbar', 'show_snackbar', width: $contentWidth),
                    ($_GET['action'] ?? '') === 'show_snackbar' ? new Snackbar('Enregistré avec succès.') : new Container(),

                    $caption('BottomSheet — panneau modal, tap en dehors pour fermer'),
                    new Button('Ouvrir le BottomSheet', BottomSheet::openAction('demo-sheet'), width: $contentWidth),
                    new BottomSheet(
                        'demo-sheet',
                        Flex::column([
                            new Text('Un vrai BottomSheet', Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
                            new Padding(
                                EdgeInsets::only(top: Tokens::SPACE_SM),
                                new Text('État ouvert/fermé géré 100% côté client — aucun aller-retour réseau.', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                            ),
                            new Padding(
                                EdgeInsets::only(top: Tokens::SPACE_LG),
                                new Button('Fermer', BottomSheet::closeAction('demo-sheet'), width: $contentWidth),
                            ),
                        ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
                    ),

                    $caption('Chip — filtre/tag'),
                    Flex::row([
                        new Chip('Tous', selected: true),
                        new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), new Chip('Actifs')),
                        new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), new Chip('Archivés', onDismiss: 'chip_dismiss')),
                    ]),

                    $caption('Badge — compteur/statut'),
                    Flex::row([
                        new Stack([
                            new IconCircle('notifications'),
                            new Positioned(new Badge(3), top: 0.0, right: 0.0),
                        ]),
                        new Padding(EdgeInsets::only(left: Tokens::SPACE_XL), new Stack([
                            new IconCircle('mail'),
                            new Positioned(new Badge(), top: 4.0, right: 4.0),
                        ])),
                    ]),

                    $caption('Skeleton — placeholder de chargement'),
                    Flex::row([
                        Skeleton::circle(48.0),
                        new Padding(
                            EdgeInsets::only(left: Tokens::SPACE_MD),
                            Skeleton::lines(2, $contentWidth - 48.0 - Tokens::SPACE_MD),
                        ),
                    ]),

                    $caption('Table'),
                    new Table(
                        rows: [['Casque sans fil', '89,90 €'], ['Montre connectée', '149,00 €']],
                        headers: ['Produit', 'Prix'],
                    ),

                    $caption('Textarea — EditText multiligne réel'),
                    new TextField('note', $note, 'Un commentaire...', multiline: true),

                    $caption('Icon — jeu étendu (2235 glyphes disponibles, voir MaterialIcons.php)'),
                    new Wrap(array_map(
                        static fn (string $name): Widget => new Icon($name, 22.0, Tokens::inkSecondary()->toHex()),
                        ['check', 'close', 'search', 'favorite', 'star', 'delete', 'edit', 'download', 'upload', 'share', 'event', 'schedule', 'mail', 'phone', 'lock', 'notifications', 'info', 'visibility'],
                    ), spacing: Tokens::SPACE_MD, runSpacing: Tokens::SPACE_MD),

                    $caption('RichText — styles mixés dans un seul paragraphe'),
                    new RichText([
                        new TextSpan('PhpNitro rend en '),
                        new TextSpan('natif', bold: true, color: Tokens::success()->toHex()),
                        new TextSpan(', pas en WebView — voir les '),
                        new TextSpan('conditions', color: Tokens::inkSecondary()->toHex(), action: 'navigate:widgets'),
                        new TextSpan('.'),
                    ], fontSize: Tokens::TEXT_BODY, color: Tokens::ink()->toHex()),

                    new Divider(),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );
    }
}
