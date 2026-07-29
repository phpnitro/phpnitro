<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeButton;
use Engine\Native\NativeScaffold;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * The native conversion of WidgetsMediaPage.php's AudioPlayer section — a
 * real android.media.MediaPlayer with actual play/pause state (see
 * NativeDeviceBridge.kt's playAudio()/pauseAudio()), not a WebView hosting
 * a Chromium <audio> element. The URL is built as an absolute
 * http://127.0.0.1:port/... address back to this same PhpServer instance
 * (via $_SERVER['HTTP_HOST'], which NativeRenderPocActivity's own request
 * already carries) since MediaPlayer needs a real URI, not a root-relative
 * path.
 *
 * VideoPlayer and GoogleTranslate are NOT ported here — genuinely bigger
 * pieces of work, not a scoping shortcut:
 * - VideoPlayer would need a real android.widget.VideoView (or ExoPlayer)
 *   overlaid at a rect the same way NativeTextField's EditText is, with
 *   its own show/hide/position lifecycle across scroll and navigation.
 * - GoogleTranslate is Google's JS/iframe website-translator widget —
 *   there's no native equivalent short of embedding ML Kit Translate (a
 *   different SDK entirely, not yet a project dependency) or a WebView
 *   (which would defeat the point of this pipeline).
 * Both stay on the WebView path until that work happens.
 */
final class NativeWidgetsMediaScreen
{
    public static function build(float $screenWidth, float $screenHeight): RenderNode
    {
        $isPlaying = ($_GET['audio_state'] ?? 'paused') === 'playing';
        $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
        $audioUrl = "http://{$host}/assets/audio/beep.wav";

        $caption = static fn (string $text): RenderNode => new RenderPadding(
            EdgeInsets::only(top: Tokens::SPACE_LG, bottom: Tokens::SPACE_SM),
            new RenderText($text, Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex(), bold: true, letterSpacing: 0.04),
        );

        $body = new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    $caption('AudioPlayer — android.media.MediaPlayer réel'),
                    $isPlaying
                        ? new NativeButton('Pause', 'media:pause', icon: 'pause')
                        : new NativeButton('Lire', "media:play:{$audioUrl}", icon: 'play_arrow'),
                    $caption('VideoPlayer / GoogleTranslate'),
                    new RenderText(
                        "Nécessitent un vrai VideoView/ExoPlayer et ML Kit Translate (SDK non encore intégré) — restent sur le pipeline WebView pour l'instant.",
                        Tokens::TEXT_BODY_SMALL,
                        Tokens::inkMuted()->toHex(),
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
            appBar: new NativeAppBar($screenWidth, 'Média', backAction: 'back'),
        );
    }
}
