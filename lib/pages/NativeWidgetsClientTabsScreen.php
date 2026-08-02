<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Flexible;
use Engine\Native\MainAxisAlignment;
use Engine\Native\AppBar;
use Engine\Native\Scaffold;
use Engine\Native\ClientTabs;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Tappable;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * ClientTabs' demo — three panels, all delivered in this one
 * response, switched with zero network call. Unlike every other
 * interactive widget in this showcase (a tap always triggers a refetch),
 * tapping a tab header here never shows up in NativeRenderPoc's PERF log
 * lines at all — there's nothing to log, no request was made. Try it in
 * airplane mode: it still works.
 */
final class NativeWidgetsClientTabsScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        $labels = ['Aperçu', 'Détails', 'Avis'];
        $panels = [
            self::panel('Aperçu du produit', ["Ce contenu, les deux autres onglets, et l'onglet actif ont tous été envoyés dans cette même réponse. Changer d'onglet ne fait aucun aller-retour serveur."]),
            self::panel('Détails techniques', ['Poids : 350g', 'Dimensions : 12 × 8 × 4 cm', 'Garantie : 2 ans']),
            self::panel('Avis clients', ['★★★★★ (128 avis)', '"Exactement ce qu\'il me fallait." — Client vérifié']),
        ];

        $tabsKey = 'demo_tabs';
        $headers = Flex::row(
            array_map(
                static fn (int $index, string $label): Widget => new Flexible(
                    new Tappable(
                        new Container(
                            new Padding(
                                EdgeInsets::symmetric(vertical: Tokens::SPACE_MD),
                                new Text($label, Tokens::TEXT_BODY_SMALL, Tokens::ink()->toHex(), bold: true),
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

        $body = new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    new Text('Les trois onglets ci-dessous sont déjà tous chargés — la sélection reste sur l\'appareil.', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_LG), $headers),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ClientTabs($tabsKey, $panels),
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
            appBar: new AppBar($screenWidth, 'Onglets (état client)', backAction: 'back'),
        );
    }

    /**
     * @param string[] $bodyLines
     */
    private static function panel(string $title, array $bodyLines): Widget
    {
        return new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_LG),
                Flex::column([
                    new Text($title, Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        Flex::column(array_map(
                            static fn (string $line): Widget => new Text($line, Tokens::TEXT_BODY, Tokens::inkSecondary()->toHex()),
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
