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
 * that file's docblock calls out: a route param, '/product/{id}' there,
 * "navigate:product/42" -> ?id=42 here (NativeRenderPocActivity splits
 * "product/42" into screen+id before fetching; see its docblock).
 */
final class NativeProductScreen
{
    public static function build(float $screenWidth, string $id): Widget
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
                                    new Text('Route param réel — /product/{id}', Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex()),
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
