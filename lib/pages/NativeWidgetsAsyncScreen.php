<?php

namespace Engine\App;

use Engine\Native\AsyncTask;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\AppBar;
use Engine\Native\Button;
use Engine\Native\Scaffold;
use Engine\Native\Async;
use Engine\Native\Center;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Spinner;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * Async's demo — Flutter's FutureBuilder, backed by a real
 * proc_open() subprocess (AsyncDemoHandler::fetchSlowData() genuinely
 * sleep()s in its own OS process, confirmed on-device: a diagnostic route
 * measured the spawning request returning in ~1ms while the child kept
 * running for its full duration). The spinner shown while pending is
 * Spinner — the same indeterminate spinner primitive, animating
 * client-side with zero refetch of its own while Canvas::pollAgain()
 * drives the actual re-checks every 400ms.
 */
final class NativeWidgetsAsyncScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        $taskKey = 'async_demo_' . session_id();

        if (($_GET['action'] ?? null) === 'async_reset') {
            AsyncTask::reset($taskKey);
        }

        $loading = Flex::column([
            new Center(new Spinner(40.0)),
            new Padding(
                EdgeInsets::only(top: Tokens::SPACE_MD),
                new Text('Calcul en cours dans un processus séparé (3s)...', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
            ),
        ], crossAxisAlignment: CrossAxisAlignment::CENTER);

        $async = new Async(
            $taskKey,
            AsyncDemoHandler::class,
            'fetchSlowData',
            [3],
            $loading,
            static fn (array $data): Widget => Flex::column([
                new Text('Terminé !', Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
                new Padding(
                    EdgeInsets::only(top: Tokens::SPACE_SM),
                    new Text($data['message'], Tokens::TEXT_BODY, Tokens::inkSecondary()->toHex()),
                ),
                new Padding(
                    EdgeInsets::only(top: Tokens::SPACE_SM),
                    new Text("Calculé à {$data['computedAt']}", Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex()),
                ),
                new Padding(
                    EdgeInsets::only(top: Tokens::SPACE_LG),
                    new Button('Relancer', 'async_reset'),
                ),
            ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
        );

        $body = new Container(
            new Padding(EdgeInsets::all(Tokens::SPACE_XL), $async),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new Scaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new AppBar($screenWidth, 'Async (Isolates)', backAction: 'back'),
        );
    }
}
