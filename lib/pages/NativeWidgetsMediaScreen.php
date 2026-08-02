<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\AppBar;
use Engine\Native\Button;
use Engine\Native\Divider;
use Engine\Native\Scaffold;
use Engine\Native\SelectBox;
use Engine\Native\VideoPlayer;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Tappable;
use Engine\Native\Text;
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
 * TextField's EditText already uses.
 *
 * GoogleTranslate is real too — but not via Google's JS/iframe web
 * widget. ML Kit Translate (NativeDeviceBridge.kt's translateText())
 * does real on-device translation, no API key, no network dependency
 * once the language model is cached — genuinely more "native" than what
 * it replaces, not a workaround for it.
 *
 * FutureBuilder needs no native equivalent: its whole point is "load
 * once per mount, don't re-poll" — every native screen render already
 * IS a one-shot fetch, there's no separate concept to demonstrate.
 * LinkWrap needs no dedicated widget either — Tappable already
 * wraps any Widget in a hit region, which is all LinkWrap ever was.
 */
final class NativeWidgetsMediaScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
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

        $caption = static fn (string $text): Widget => new Padding(
            EdgeInsets::only(top: Tokens::SPACE_LG, bottom: Tokens::SPACE_SM),
            new Text($text, Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex(), bold: true, letterSpacing: 0.04),
        );

        $body = new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    $caption('AudioPlayer — android.media.MediaPlayer réel'),
                    $isPlaying
                        ? new Button('Pause', 'media:pause', icon: 'pause')
                        : new Button('Lire', "media:play:{$audioUrl}", icon: 'play_arrow'),
                    $caption('VideoPlayer — android.widget.VideoView réel'),
                    new VideoPlayer($videoUrl, $contentWidth),

                    $caption('GoogleTranslate — ML Kit Translate réel, sur l\'appareil'),
                    new Text("« {$sourceText} »", Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new SelectBox('translate_lang', [
                            'en' => 'Anglais',
                            'es' => 'Espagnol',
                            'de' => 'Allemand',
                        ], $targetLanguage),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new Button('Traduire', "translate:{$targetLanguage}", meta: ['text' => $sourceText]),
                    ),
                    ...($translateOut !== null ? [new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new Text($translateOut, Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true))] : []),

                    new Divider(),
                    $caption('FutureBuilder — chaque écran natif est déjà un chargement unique, sans re-polling'),
                    $caption('LinkWrap — Tappable enveloppe déjà n\'importe quel widget'),
                    new Tappable(
                        new Text('Toute cette zone est cliquable →', Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true),
                        'navigate:widgets',
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
            appBar: new AppBar($screenWidth, 'Média', backAction: 'back'),
        );
    }
}
