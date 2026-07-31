<?php

namespace Engine\App;

use Engine\Native\AsyncTask;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeButton;
use Engine\Native\NativeScaffold;
use Engine\Native\RenderAsync;
use Engine\Native\RenderCenter;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderSpinner;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * RenderAsync's demo — Flutter's FutureBuilder, backed by a real
 * proc_open() subprocess (AsyncDemoHandler::fetchSlowData() genuinely
 * sleep()s in its own OS process, confirmed on-device: a diagnostic route
 * measured the spawning request returning in ~1ms while the child kept
 * running for its full duration). The spinner shown while pending is
 * RenderSpinner — the same indeterminate spinner primitive, animating
 * client-side with zero refetch of its own while NativeCanvas::pollAgain()
 * drives the actual re-checks every 400ms.
 */
final class NativeWidgetsAsyncScreen
{
    public static function build(float $screenWidth, float $screenHeight): RenderNode
    {
        $taskKey = 'async_demo_' . session_id();

        if (($_GET['action'] ?? null) === 'async_reset') {
            AsyncTask::reset($taskKey);
        }

        $loading = RenderFlex::column([
            new RenderCenter(new RenderSpinner(40.0)),
            new RenderPadding(
                EdgeInsets::only(top: Tokens::SPACE_MD),
                new RenderText('Calcul en cours dans un processus séparé (3s)...', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
            ),
        ], crossAxisAlignment: CrossAxisAlignment::CENTER);

        $async = new RenderAsync(
            $taskKey,
            AsyncDemoHandler::class,
            'fetchSlowData',
            [3],
            $loading,
            static fn (array $data): RenderNode => RenderFlex::column([
                new RenderText('Terminé !', Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
                new RenderPadding(
                    EdgeInsets::only(top: Tokens::SPACE_SM),
                    new RenderText($data['message'], Tokens::TEXT_BODY, Tokens::inkSecondary()->toHex()),
                ),
                new RenderPadding(
                    EdgeInsets::only(top: Tokens::SPACE_SM),
                    new RenderText("Calculé à {$data['computedAt']}", Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex()),
                ),
                new RenderPadding(
                    EdgeInsets::only(top: Tokens::SPACE_LG),
                    new NativeButton('Relancer', 'async_reset'),
                ),
            ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
        );

        $body = new RenderContainer(
            new RenderPadding(EdgeInsets::all(Tokens::SPACE_XL), $async),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new NativeScaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new NativeAppBar($screenWidth, 'Async (Isolates)', backAction: 'back'),
        );
    }
}
