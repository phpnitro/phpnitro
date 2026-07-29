<?php

namespace Engine\App;

use Backend\Kernel;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeCard;
use Engine\Native\NativeScaffold;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
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
    public static function build(float $screenWidth, float $screenHeight): RenderNode
    {
        $request = Request::create('/api/hello');
        $data = json_decode((new Kernel())->handle($request)->getContent(), true);
        $message = $data['message'] ?? 'Réponse inattendue du backend.';

        $body = new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    new RenderText('Appel en-process — Backend\\Kernel', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new NativeCard(new RenderText($message, Tokens::TEXT_BODY, Tokens::ink()->toHex())),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new NativeScaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new NativeAppBar($screenWidth, 'Backend PHP', backAction: 'back'),
            bottomNav: NativeAppShell::bottomNav($screenWidth, 'api'),
        );
    }
}
