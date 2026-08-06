<?php

namespace Engine\App;

use Engine\Native\EdgeInsets;
use Engine\Native\AppBar;
use Engine\Native\ListTile;
use Engine\Native\Scaffold;
use Engine\Native\Container;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Reorderable;
use Engine\Native\Tokens;

/**
 * The second continuous gesture, same split as Dismissible: PHP
 * never sees the drag, only its outcome. Long-press an item, drag it —
 * NativeCanvasView.kt tracks the whole thing (long-press detection, live
 * follow, slot-swapping, settle animation) client-side, and only calls
 * back once the finger lifts, with the final key order.
 */
final class NativeWidgetsReorderScreen
{
    /** @return array<string, string> id => label */
    public static function initialItems(): array
    {
        return [
            '1' => '1. Écrire la spec',
            '2' => '2. Construire le prototype',
            '3' => '3. Tester sur device réel',
            '4' => '4. Écrire la doc',
            '5' => '5. Publier',
        ];
    }

    /**
     * @param array<string, string> $items id => label, in current order
     */
    public static function build(float $screenWidth, float $screenHeight, array $items): Widget
    {
        $rows = [];
        foreach ($items as $id => $label) {
            $rows[$id] = new Padding(
                EdgeInsets::only(bottom: Tokens::SPACE_MD),
                new ListTile($label, 'Appui long puis glisse pour réordonner', 'drag_indicator', leadingColor: Tokens::inkSecondary()),
            );
        }

        $body = new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                new Reorderable('todo', $rows, 'reorder'),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new Scaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new AppBar($screenWidth, 'Étapes (glisser pour réordonner)', backAction: 'back'),
        );
    }
}
