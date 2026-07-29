<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeButton;
use Engine\Native\NativeProgressBar;
use Engine\Native\NativeScaffold;
use Engine\Native\NativeSelectBox;
use Engine\Native\NativeTextField;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * The native conversion of WidgetsStepperPage.php — a real 3-step wizard,
 * not a static mockup. Engine\Stepper's server-side $state (itself
 * session-backed by the Screen base class) becomes plain $_SESSION reads/
 * writes in public/index.php (see its "widgets_stepper_*" handling) since
 * this pipeline has no per-request Screen object to hold it. NativeButton's
 * "submit:" collects every NativeTextField/NativeSelectBox's current value
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
    public static function build(float $screenWidth, float $screenHeight, int $step, array $data): RenderNode
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;

        $stepBody = match ($step) {
            0 => RenderFlex::column([
                new NativeTextField('name', $data['name'] ?? '', 'Nom complet'),
                new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_MD), new NativeTextField('email', $data['email'] ?? '', 'Email')),
            ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            1 => new NativeSelectBox('plan', [
                'free' => 'Gratuit',
                'pro' => 'Pro',
                'enterprise' => 'Entreprise',
            ], $data['plan'] ?? 'free'),
            default => RenderFlex::column([
                new RenderText('Récapitulatif', Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
                new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_SM), new RenderText('Nom : ' . ($data['name'] ?? '—'), Tokens::TEXT_BODY, Tokens::inkSecondary()->toHex())),
                new RenderPadding(EdgeInsets::only(top: 4), new RenderText('Email : ' . ($data['email'] ?? '—'), Tokens::TEXT_BODY, Tokens::inkSecondary()->toHex())),
                new RenderPadding(EdgeInsets::only(top: 4), new RenderText('Formule : ' . ($data['plan'] ?? '—'), Tokens::TEXT_BODY, Tokens::inkSecondary()->toHex())),
            ]),
        };

        $backAction = $step > 0 ? 'submit:stepper_back' : null;
        $nextAction = $step < self::LAST_STEP ? 'submit:stepper_next' : 'submit:stepper_reset';
        $nextLabel = $step < self::LAST_STEP ? 'Suivant' : 'Recommencer';

        $body = new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    new RenderText('Étape ' . ($step + 1) . '/' . (self::LAST_STEP + 1) . ' — ' . self::LABELS[$step], Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex(), bold: true),
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_SM), new NativeProgressBar($contentWidth, ($step + 1) / (self::LAST_STEP + 1))),
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_XL), $stepBody),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        RenderFlex::row([
                            ...($backAction !== null ? [new NativeButton('Retour', $backAction, background: Tokens::surfaceMuted(), foreground: Tokens::ink())] : []),
                            new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_MD), new NativeButton($nextLabel, $nextAction)),
                        ]),
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
            appBar: new NativeAppBar($screenWidth, 'Stepper', backAction: 'back'),
        );
    }
}
