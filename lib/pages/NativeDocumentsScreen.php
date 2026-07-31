<?php

namespace Engine\App;

use Engine\Color;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Flexible;
use Engine\Native\NativeButton;
use Engine\Native\NativeIconCircle;
use Engine\Native\NativeListTile;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderIcon;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * Modeled directly on captures/Documents.png — a minimalist checklist
 * screen: near-black ink on white, thin gray borders instead of shadows,
 * a pill-radius black CTA at the bottom. See docs/proposals/moteur-rendu-natif.md.
 *
 * Built from the NativeIconCircle/NativeListTile/NativeButton widget
 * layer instead of hand-rolled RenderContainer/RenderCenter composition.
 */
final class NativeDocumentsScreen
{
    public static function build(float $screenWidth, int $tapCount): RenderNode
    {
        // $tapCount stands in for "how many required documents are done"
        // so the tappable button still demonstrates a genuine server
        // round-trip instead of a hardcoded state.
        $requiredDone = min($tapCount, 2);

        $documentRow = static fn (string $title, string $subtitle, bool $required, bool $done): NativeListTile => new NativeListTile(
            $title,
            $subtitle,
            $done ? 'check_circle' : 'description',
            leadingBackground: $done ? Tokens::successMuted() : Tokens::surfaceMuted(),
            leadingColor: $done ? Tokens::success() : Tokens::inkSecondary(),
            trailingIcon: $done ? 'check' : 'add',
            trailingBackground: $done ? Tokens::successMuted() : Tokens::ink(),
            trailingColor: $done ? Tokens::success() : Color::white(),
            borderColor: $done ? Color::green(400) : Tokens::border(),
            borderWidth: $done ? 1.5 : 1.0,
            // The red tracked-uppercase badge wins over $subtitle when a
            // document is required — same "one or the other" rule the
            // hand-rolled version had.
            subtitleNode: $required
                ? new RenderText('OBLIGATOIRE', Tokens::TEXT_CAPTION, Tokens::danger()->toHex(), bold: true, letterSpacing: 0.04)
                : null,
        );

        return new RenderContainer(
            RenderFlex::column([
                // Top bar: back circle + thin progress line, then a step caption.
                new RenderPadding(
                    EdgeInsets::all(Tokens::SPACE_XL),
                    RenderFlex::column([
                        RenderFlex::row([
                            new NativeIconCircle('arrow_back', action: 'back'),
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
                            // Real tappable region — server-driven state,
                            // same phase-3 round-trip as before. Once both
                            // required documents are marked done,
                            // "Continuer" pushes a real navigation
                            // (NativeRenderPocActivity intercepts
                            // "navigate:" client-side and switches
                            // screens) instead of just incrementing
                            // forever.
                            new RenderPadding(
                                EdgeInsets::only(top: Tokens::SPACE_XL),
                                new NativeButton(
                                    $requiredDone >= 2 ? 'Continuer' : "Valider un document ({$requiredDone}/2)",
                                    $requiredDone >= 2 ? 'navigate:otp' : 'increment',
                                    width: $screenWidth - 2 * Tokens::SPACE_XL,
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
