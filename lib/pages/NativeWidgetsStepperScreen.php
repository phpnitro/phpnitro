<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\AppBar;
use Engine\Native\Button;
use Engine\Native\ProgressBar;
use Engine\Native\Scaffold;
use Engine\Native\SelectBox;
use Engine\Native\TextField;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * The native conversion of WidgetsStepperPage.php — a real 3-step wizard,
 * not a static mockup. Engine\Stepper's server-side $state (itself
 * session-backed by the Screen base class) becomes plain $_SESSION reads/
 * writes in public/index.php (see its "widgets_stepper_*" handling) since
 * this pipeline has no per-request Screen object to hold it. Button's
 * "submit:" collects every TextField/SelectBox's current value
 * client-side and sends them all with each Back/Next/Reset tap, same as
 * WidgetsStepperPage.php's onStepperBack()/onStepperNext() merging
 * whatever the just-submitted step's form carried into the accumulated
 * $data.
 */
final class NativeWidgetsStepperScreen
{
    private const LAST_STEP = 2;
    private const LABELS = ['Compte', 'Préférences', 'Résumé'];

    /**
     * @param array<string, string> $data
     */
    public static function build(float $screenWidth, float $screenHeight, int $step, array $data): Widget
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;

        $stepBody = match ($step) {
            0 => Flex::column([
                new TextField('name', $data['name'] ?? '', 'Nom complet'),
                new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new TextField('email', $data['email'] ?? '', 'Email')),
            ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            1 => new SelectBox('plan', [
                'free' => 'Gratuit',
                'pro' => 'Pro',
                'enterprise' => 'Entreprise',
            ], $data['plan'] ?? 'free'),
            default => Flex::column([
                new Text('Récapitulatif', Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
                new Padding(EdgeInsets::only(top: Tokens::SPACE_SM), new Text('Nom : ' . ($data['name'] ?? '—'), Tokens::TEXT_BODY, Tokens::inkSecondary()->toHex())),
                new Padding(EdgeInsets::only(top: 4), new Text('Email : ' . ($data['email'] ?? '—'), Tokens::TEXT_BODY, Tokens::inkSecondary()->toHex())),
                new Padding(EdgeInsets::only(top: 4), new Text('Formule : ' . ($data['plan'] ?? '—'), Tokens::TEXT_BODY, Tokens::inkSecondary()->toHex())),
            ]),
        };

        $backAction = $step > 0 ? 'submit:stepper_back' : null;
        $nextAction = $step < self::LAST_STEP ? 'submit:stepper_next' : 'submit:stepper_reset';
        $nextLabel = $step < self::LAST_STEP ? 'Suivant' : 'Recommencer';

        $body = new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    new Text('Étape ' . ($step + 1) . '/' . (self::LAST_STEP + 1) . ' — ' . self::LABELS[$step], Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex(), bold: true),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_SM), new ProgressBar($contentWidth, ($step + 1) / (self::LAST_STEP + 1))),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_XL), $stepBody),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        Flex::row([
                            ...($backAction !== null ? [new Button('Retour', $backAction, background: Tokens::surfaceMuted(), foreground: Tokens::ink())] : []),
                            new Padding(EdgeInsets::only(left: Tokens::SPACE_MD), new Button($nextLabel, $nextAction)),
                        ]),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new Scaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new AppBar($screenWidth, 'Stepper', backAction: 'back'),
        );
    }
}
