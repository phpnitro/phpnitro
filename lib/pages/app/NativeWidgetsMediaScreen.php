<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeButton;
use Engine\Native\NativeScaffold;
use Engine\Native\NativeSelectBox;
use Engine\Native\NativeVideoPlayer;
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
 * VideoPlayer is real too now — a genuine android.widget.VideoView
 * overlaid at the tapped rect (NativeRenderPocActivity's
 * showVideoOverlay()), same "no DOM element to attach to" idiom
 * NativeTextField's EditText already uses.
 *
 * GoogleTranslate is real too — but not via Google's JS/iframe web
 * widget. ML Kit Translate (NativeDeviceBridge.kt's translateText())
 * does real on-device translation, no API key, no network dependency
 * once the language model is cached — genuinely more "native" than what
 * it replaces, not a workaround for it.
 */
final class NativeWidgetsMediaScreen
{
    public static function build(float $screenWidth, float $screenHeight): RenderNode
    {
        $isPlaying = ($_GET['audio_state'] ?? 'paused') === 'playing';
        $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
        $audioUrl = "http://{$host}/assets/audio/beep.wav";
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;
        // Same public-domain sample WidgetsMediaPage.php's VideoPlayer demo uses.
        $videoUrl = 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4';
        $sourceText = 'Bonjour le monde ! Ceci est PhpNitro.';
        $targetLanguage = $_GET['translate_lang'] ?? 'en';
        $translateOut = $_GET['translate_out'] ?? null;

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
                    $caption('VideoPlayer — android.widget.VideoView réel'),
                    new NativeVideoPlayer($videoUrl, $contentWidth),

                    $caption('GoogleTranslate — ML Kit Translate réel, sur l\'appareil'),
                    new RenderText("« {$sourceText} »", Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeSelectBox('translate_lang', [
                            'en' => 'Anglais',
                            'es' => 'Espagnol',
                            'de' => 'Allemand',
                        ], $targetLanguage),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeButton('Traduire', "translate:{$targetLanguage}", meta: ['text' => $sourceText]),
                    ),
                    ...($translateOut !== null ? [new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_MD), new RenderText($translateOut, Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true))] : []),

                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new NativeButton('Voir sur WebView', 'webview:/widgets/media', background: Tokens::surfaceMuted(), foreground: Tokens::ink()),
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
