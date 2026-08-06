<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Flexible;
use Engine\Native\Card;
use Engine\Native\IconCircle;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * The native conversion of ProductPage.php — demonstrates the same thing
 * that file's docblock calls out: route params, '/product/{id}' there,
 * "navigate:product?id=42&tab=reviews" here — a screen token is
 * "name?query", a REAL query string, so this reads $tab straight
 * alongside $id with no extra parsing of its own (NativeRenderPocActivity
 * splits "product?id=42&tab=reviews" into screen + that query string
 * before fetching, re-encoding each pair individually; see its own
 * docblock above screenToken in fetchDrawCommands()).
 */
final class NativeProductScreen
{
    public static function build(float $screenWidth, string $id, ?string $tab): Widget
    {
        return new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    new IconCircle('arrow_back', action: 'back'),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new Card(Flex::row([
                            new IconCircle('inventory_2', 48, background: Tokens::surfaceMuted()),
                            new Flexible(new Padding(EdgeInsets::only(left: Tokens::SPACE_MD), Flex::column([
                                new Text("Produit #{$id}", Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
                                new Padding(
                                    EdgeInsets::only(top: 2),
                                    new Text(
                                        $tab !== null
                                            ? "Route multi-paramètres réelle — /product?id={$id}&tab={$tab}"
                                            : 'Route param réel — /product?id={id}',
                                        Tokens::TEXT_CAPTION,
                                        Tokens::inkMuted()->toHex(),
                                    ),
                                ),
                            ]))),
                        ], crossAxisAlignment: CrossAxisAlignment::CENTER)),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );
    }
}
