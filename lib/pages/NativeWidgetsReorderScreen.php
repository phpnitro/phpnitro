<?php

namespace Engine\App;

use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeListTile;
use Engine\Native\NativeScaffold;
use Engine\Native\RenderContainer;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderReorderable;
use Engine\Native\Tokens;

/**
 * The second continuous gesture, same split as RenderDismissible: PHP
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
    public static function build(float $screenWidth, float $screenHeight, array $items): RenderNode
    {
        $rows = [];
        foreach ($items as $id => $label) {
            $rows[$id] = new RenderPadding(
                EdgeInsets::only(bottom: Tokens::SPACE_MD),
                new NativeListTile($label, 'Appui long puis glisse pour réordonner', 'drag_indicator', leadingColor: Tokens::inkSecondary()),
            );
        }

        $body = new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                new RenderReorderable('todo', $rows, 'reorder'),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new NativeScaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new NativeAppBar($screenWidth, 'Étapes (glisser pour réordonner)', backAction: 'back'),
        );
    }
}
