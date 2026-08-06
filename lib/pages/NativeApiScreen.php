<?php

namespace Engine\App;

use Backend\Kernel;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\AppBar;
use Engine\Native\Card;
use Engine\Native\Scaffold;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Text;
use Engine\Native\Tokens;
use Symfony\Component\HttpFoundation\Request;

/**
 * The native conversion of ApiPage.php — same in-process call to
 * Backend\Kernel (no HTTP round-trip, both live in the same PHP process),
 * proving the native pipeline reaches the same backend the WebView
 * pipeline does, not a parallel one.
 */
final class NativeApiScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        $request = Request::create('/api/hello');
        $data = json_decode((new Kernel())->handle($request)->getContent(), true);
        $message = $data['message'] ?? 'Réponse inattendue du backend.';

        $body = new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    new Text('Appel en-process — Backend\\Kernel', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new Card(new Text($message, Tokens::TEXT_BODY, Tokens::ink()->toHex())),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new Scaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new AppBar($screenWidth, 'Backend PHP', backAction: 'back'),
            bottomNav: NativeAppShell::bottomNav($screenWidth, 'api'),
        );
    }
}
