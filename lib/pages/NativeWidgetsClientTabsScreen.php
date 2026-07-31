<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Flexible;
use Engine\Native\MainAxisAlignment;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeScaffold;
use Engine\Native\RenderClientTabs;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderTappable;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * RenderClientTabs' demo — three panels, all delivered in this one
 * response, switched with zero network call. Unlike every other
 * interactive widget in this showcase (a tap always triggers a refetch),
 * tapping a tab header here never shows up in NativeRenderPoc's PERF log
 * lines at all — there's nothing to log, no request was made. Try it in
 * airplane mode: it still works.
 */
final class NativeWidgetsClientTabsScreen
{
    public static function build(float $screenWidth, float $screenHeight): RenderNode
    {
        $labels = ['Aperçu', 'Détails', 'Avis'];
        $panels = [
            self::panel('Aperçu du produit', ["Ce contenu, les deux autres onglets, et l'onglet actif ont tous été envoyés dans cette même réponse. Changer d'onglet ne fait aucun aller-retour serveur."]),
            self::panel('Détails techniques', ['Poids : 350g', 'Dimensions : 12 × 8 × 4 cm', 'Garantie : 2 ans']),
            self::panel('Avis clients', ['★★★★★ (128 avis)', '"Exactement ce qu\'il me fallait." — Client vérifié']),
        ];

        $tabsKey = 'demo_tabs';
        $headers = RenderFlex::row(
            array_map(
                static fn (int $index, string $label): RenderNode => new Flexible(
                    new RenderTappable(
                        new RenderContainer(
                            new RenderPadding(
                                EdgeInsets::symmetric(vertical: Tokens::SPACE_MD),
                                new RenderText($label, Tokens::TEXT_BODY_SMALL, Tokens::ink()->toHex(), bold: true),
                            ),
                            width: null,
                        ),
                        "clientTab:{$tabsKey}:{$index}",
                    ),
                    1,
                ),
                array_keys($labels),
                $labels,
            ),
            mainAxisAlignment: MainAxisAlignment::CENTER,
        );

        $body = new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    new RenderText('Les trois onglets ci-dessous sont déjà tous chargés — la sélection reste sur l\'appareil.', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_LG), $headers),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new RenderClientTabs($tabsKey, $panels),
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
            appBar: new NativeAppBar($screenWidth, 'Onglets (état client)', backAction: 'back'),
        );
    }

    /**
     * @param string[] $bodyLines
     */
    private static function panel(string $title, array $bodyLines): RenderNode
    {
        return new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_LG),
                RenderFlex::column([
                    new RenderText($title, Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        RenderFlex::column(array_map(
                            static fn (string $line): RenderNode => new RenderText($line, Tokens::TEXT_BODY, Tokens::inkSecondary()->toHex()),
                            $bodyLines,
                        ), crossAxisAlignment: CrossAxisAlignment::STRETCH),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            background: Tokens::surface(),
            radius: Tokens::RADIUS_LG,
        );
    }
}
