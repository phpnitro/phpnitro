<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Flexible;
use Engine\Native\NativeCard;
use Engine\Native\NativeIconCircle;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * The native conversion of ProductPage.php — demonstrates the same thing
 * that file's docblock calls out: a route param, '/product/{id}' there,
 * "navigate:product/42" -> ?id=42 here (NativeRenderPocActivity splits
 * "product/42" into screen+id before fetching; see its docblock).
 */
final class NativeProductScreen
{
    public static function build(float $screenWidth, string $id): RenderNode
    {
        return new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    new NativeIconCircle('arrow_back', action: 'back'),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new NativeCard(RenderFlex::row([
                            new NativeIconCircle('inventory_2', 48, background: Tokens::surfaceMuted()),
                            new Flexible(new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_MD), RenderFlex::column([
                                new RenderText("Produit #{$id}", Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
                                new RenderPadding(
                                    EdgeInsets::only(top: 2),
                                    new RenderText('Route param réel — /product/{id}', Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex()),
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
