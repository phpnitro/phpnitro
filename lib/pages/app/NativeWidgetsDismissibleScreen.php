<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeListTile;
use Engine\Native\NativeScaffold;
use Engine\Native\RenderContainer;
use Engine\Native\RenderDismissible;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * The one genuinely continuous gesture in this pipeline: swiping an item
 * off never round-trips to PHP mid-drag — NativeCanvasView.kt tracks the
 * finger and translates the item's own commands live, and only calls back
 * once the swipe commits past threshold on release (see
 * RenderDismissible's docblock). PHP's job is just to persist the outcome
 * — $_SESSION here stands in for "a real list backed by a database row",
 * removed via public/index.php's handling of the "dismiss:{id}" action
 * this screen's items are built with.
 */
final class NativeWidgetsDismissibleScreen
{
    /** @return array<int, string> */
    public static function initialItems(): array
    {
        return [
            'Répondre à Aïcha',
            'Payer la facture Wave',
            'Relire le PR #42',
            'Réserver le vol Cotonou',
            'Appeler le fournisseur',
            'Préparer la démo client',
        ];
    }

    /**
     * @param array<string, string> $items id => label
     */
    public static function build(float $screenWidth, float $screenHeight, array $items): RenderNode
    {
        $rows = [];
        foreach ($items as $id => $label) {
            $rows[] = new RenderDismissible(
                new RenderPadding(
                    EdgeInsets::only(bottom: Tokens::SPACE_MD),
                    new NativeListTile($label, 'Glisse pour supprimer', 'task_alt', leadingColor: Tokens::inkSecondary()),
                ),
                "dismissible-{$id}",
                "dismiss:{$id}",
            );
        }

        $body = new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                $items === []
                    ? RenderFlex::column([
                        new RenderText('Tout est fait !', Tokens::TEXT_BODY, Tokens::inkMuted()->toHex()),
                    ])
                    : RenderFlex::column($rows, crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new NativeScaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new NativeAppBar($screenWidth, 'Tâches (glisser pour supprimer)', backAction: 'back'),
        );
    }
}
