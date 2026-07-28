<?php

namespace Engine\App;

use Engine\Color;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Flexible;
use Engine\Native\RenderCenter;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderIcon;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderTappable;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * Modeled directly on captures/Documents.png — a minimalist checklist
 * screen: near-black ink on white, thin gray borders instead of shadows,
 * a pill-radius black CTA at the bottom. See docs/proposals/moteur-rendu-natif.md.
 *
 * Extracted out of public/index.php's /native/layout-demo route once a
 * second reference screen (NativeOtpScreen) needed to live next to it —
 * keeps the route a thin dispatcher instead of one growing function.
 */
final class NativeDocumentsScreen
{
    public static function build(float $screenWidth, int $tapCount): RenderNode
    {
        // $tapCount stands in for "how many required documents are done"
        // so the tappable button still demonstrates a genuine server
        // round-trip instead of a hardcoded state.
        $requiredDone = min($tapCount, 2);

        $iconCircle = static fn (string $icon, Color $background, Color $iconColor, float $diameter = 40.0): RenderContainer => new RenderContainer(
            new RenderCenter(new RenderIcon($icon, $diameter * 0.5, $iconColor->toHex())),
            width: $diameter,
            height: $diameter,
            radius: $diameter / 2,
            background: $background,
        );

        $documentRow = static function (string $title, string $subtitle, bool $required, bool $done) use ($iconCircle): RenderContainer {
            return new RenderContainer(
                new RenderPadding(
                    EdgeInsets::symmetric(Tokens::SPACE_LG, Tokens::SPACE_MD),
                    RenderFlex::row([
                        $done
                            ? $iconCircle('check_circle', Tokens::successMuted(), Tokens::success(), 36)
                            : $iconCircle('document', Tokens::surfaceMuted(), Tokens::inkSecondary(), 36),
                        new Flexible(new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_MD), RenderFlex::column([
                            new RenderText($title, Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true),
                            new RenderPadding(
                                EdgeInsets::only(top: 3),
                                $required
                                    ? new RenderText('OBLIGATOIRE', Tokens::TEXT_CAPTION, Tokens::danger()->toHex(), bold: true, letterSpacing: 0.04)
                                    : new RenderText($subtitle, Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                            ),
                        ]))),
                        $done
                            ? $iconCircle('check', Tokens::successMuted(), Tokens::success(), 30)
                            : $iconCircle('plus', Tokens::ink(), Color::white(), 30),
                    ], crossAxisAlignment: CrossAxisAlignment::CENTER),
                ),
                background: Tokens::surface(),
                radius: Tokens::RADIUS_LG,
                borderColor: $done ? Color::green(400) : Tokens::border(),
                borderWidth: $done ? 1.5 : 1.0,
            );
        };

        return new RenderContainer(
            RenderFlex::column([
                // Top bar: back circle + thin progress line, then a step caption.
                new RenderPadding(
                    EdgeInsets::all(Tokens::SPACE_XL),
                    RenderFlex::column([
                        RenderFlex::row([
                            $iconCircle('arrow_back', Tokens::surfaceMuted(), Tokens::ink()),
                            new Flexible(new RenderPadding(
                                EdgeInsets::only(left: Tokens::SPACE_MD, top: 18),
                                new RenderContainer(height: 3, radius: 2, background: Tokens::ink()),
                            )),
                        ], crossAxisAlignment: CrossAxisAlignment::CENTER),
                        new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_SM), new RenderText('Étape 3/4', Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex())),
                        new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_LG), new RenderText('Documents requis', Tokens::TEXT_DISPLAY, Tokens::ink()->toHex(), bold: true)),
                        new RenderPadding(
                            EdgeInsets::only(top: 6),
                            new RenderText('Formats acceptés : PDF, JPG, PNG — 10 Mo max par fichier.', Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),
                        ),
                    ]),
                ),
                // Content area: light gray background, same as the capture.
                new RenderContainer(
                    new RenderPadding(
                        EdgeInsets::symmetric(Tokens::SPACE_XL, Tokens::SPACE_LG),
                        RenderFlex::column([
                            $documentRow('Permis de conduire', '', true, $requiredDone >= 1),
                            new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_MD), $documentRow('Permis moto', 'si compétence moto', false, false)),
                            new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_MD), $documentRow('Assurance professionnelle', '', true, false)),
                            new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_MD), $documentRow('Pièce d\'identité', '', true, $requiredDone >= 2)),
                            new RenderPadding(
                                EdgeInsets::only(top: Tokens::SPACE_LG),
                                new RenderContainer(
                                    new RenderPadding(
                                        EdgeInsets::all(Tokens::SPACE_MD),
                                        RenderFlex::row([
                                            new RenderIcon('warning', 18, Tokens::danger()->toHex()),
                                            new Flexible(new RenderPadding(
                                                EdgeInsets::only(left: Tokens::SPACE_SM),
                                                new RenderText('Veuillez ajouter les documents obligatoires pour continuer.', Tokens::TEXT_BODY_SMALL, Tokens::danger()->toHex()),
                                            )),
                                        ]),
                                    ),
                                    background: Color::red(50),
                                    radius: Tokens::RADIUS_MD,
                                ),
                            ),
                            // Real tappable region — server-driven state, same
                            // phase-3 round-trip as before, now standing in
                            // for "mark the next required document done".
                            new RenderPadding(
                                EdgeInsets::only(top: Tokens::SPACE_XL),
                                new RenderTappable(
                                    new RenderContainer(
                                        new RenderCenter(new RenderText(
                                            $requiredDone >= 2 ? 'Continuer' : "Valider un document ({$requiredDone}/2)",
                                            Tokens::TEXT_BODY,
                                            '#FFFFFF',
                                            bold: true,
                                        )),
                                        height: 54,
                                        background: Tokens::ink(),
                                        radius: Tokens::RADIUS_PILL,
                                    ),
                                    action: 'increment',
                                ),
                            ),
                        ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
                    ),
                    background: Tokens::surfaceMuted(),
                ),
            ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            background: Tokens::surface(),
        );
    }
}
