<?php

namespace Engine\App;

use Engine\Native\EdgeInsets;
use Engine\Native\AppBar;
use Engine\Native\ListTile;
use Engine\Native\Scaffold;
use Engine\Native\Container;
use Engine\Native\LazyList;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Tokens;

/**
 * Proves LazyList against a real 5000-row list — PHP only ever
 * builds/lays out the handful of ListTile rows within the current
 * scroll window (see LazyList's docblock), yet the whole list
 * scrolls and reports its true 5000-item height client-side. Compare
 * renderTimeMs here against a screen with 5000 statically-built widgets
 * (there isn't one — it would time out) for why this exists at all: this
 * is the one thing PageView's tap-driven pagination genuinely
 * couldn't replace.
 */
final class NativeWidgetsLazyListScreen
{
    private const ITEM_COUNT = 5000;
    private const ITEM_HEIGHT = 64.0;

    public static function build(float $screenWidth, float $screenHeight, float $scrollY): Widget
    {
        $itemBuilder = static fn (int $index): Widget => new Padding(
            EdgeInsets::symmetric(horizontal: Tokens::SPACE_MD, vertical: Tokens::SPACE_XS),
            new ListTile(
                "Élément #{$index}",
                'Construit à la volée — fenêtre de défilement réelle',
                'inventory_2',
                leadingColor: Tokens::inkSecondary(),
                trailingText: $index % 7 === 0 ? '★' : null,
            ),
        );

        $body = new Container(
            new LazyList(self::ITEM_COUNT, $itemBuilder, self::ITEM_HEIGHT, $scrollY, $screenHeight),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new Scaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new AppBar($screenWidth, self::ITEM_COUNT . ' éléments (liste virtualisée)', backAction: 'back'),
        );
    }
}
