<?php

namespace Engine\App;

use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeListTile;
use Engine\Native\NativeScaffold;
use Engine\Native\RenderContainer;
use Engine\Native\RenderLazyList;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\Tokens;

/**
 * Proves RenderLazyList against a real 5000-row list — PHP only ever
 * builds/lays out the handful of NativeListTile rows within the current
 * scroll window (see RenderLazyList's docblock), yet the whole list
 * scrolls and reports its true 5000-item height client-side. Compare
 * renderTimeMs here against a screen with 5000 statically-built widgets
 * (there isn't one — it would time out) for why this exists at all: this
 * is the one thing NativePageView's tap-driven pagination genuinely
 * couldn't replace.
 */
final class NativeWidgetsLazyListScreen
{
    private const ITEM_COUNT = 5000;
    private const ITEM_HEIGHT = 64.0;

    public static function build(float $screenWidth, float $screenHeight, float $scrollY): RenderNode
    {
        $itemBuilder = static fn (int $index): RenderNode => new RenderPadding(
            EdgeInsets::symmetric(horizontal: Tokens::SPACE_MD, vertical: Tokens::SPACE_XS),
            new NativeListTile(
                "Élément #{$index}",
                'Construit à la volée — fenêtre de défilement réelle',
                'inventory_2',
                leadingColor: Tokens::inkSecondary(),
                trailingText: $index % 7 === 0 ? '★' : null,
            ),
        );

        $body = new RenderContainer(
            new RenderLazyList(self::ITEM_COUNT, $itemBuilder, self::ITEM_HEIGHT, $scrollY, $screenHeight),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new NativeScaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new NativeAppBar($screenWidth, self::ITEM_COUNT . ' éléments (liste virtualisée)', backAction: 'back'),
        );
    }
}
