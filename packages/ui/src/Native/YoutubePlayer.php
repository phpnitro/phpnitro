<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Native;

/**
 * A tap-to-play YouTube embed — the exact same idiom VideoPlayer already
 * uses (a placeholder box painted on the Canvas; tapping it tells
 * NativeRenderPocActivity to overlay a real Android View at that rect,
 * see its own showYoutubeOverlay()), just a WebView loaded with
 * YouTube's IFrame Player instead of a VideoView + raw media URL —
 * YouTube requires their own embed player, there's no direct .mp4 URL to
 * hand a plain VideoView. This is the same technique every current
 * youtube_player_flutter/react-native-youtube-iframe package uses under
 * the hood, not a workaround specific to this framework.
 *
 * $videoId is the 11-character id from a YouTube URL
 * ("https://www.youtube.com/watch?v=dQw4w9WgXcQ" -> "dQw4w9WgXcQ"), not
 * a full URL.
 */
final class YoutubePlayer implements Widget
{
    private readonly Widget $content;

    public function __construct(
        string $videoId,
        float $width,
        float $height = 200.0,
        string $label = 'Lire la vidéo YouTube',
    ) {
        $box = new Container(
            new Center(Flex::row([
                new Icon('play_circle', 32.0, Tokens::ink()->toHex()),
                new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), new Text($label, Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true)),
            ], crossAxisAlignment: CrossAxisAlignment::CENTER)),
            width: $width,
            height: $height,
            background: Tokens::surfaceMuted(),
            radius: Tokens::RADIUS_LG,
        );

        $this->content = new Tappable($box, "youtube:play:{$videoId}");
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->content->layout($constraints);
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $this->content->paint($canvas, $x, $y);
    }
}
