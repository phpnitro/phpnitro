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
 * The native-tree equivalent of Engine\VideoPlayer — there's no DOM
 * <video> element for a Canvas, so tapping this box tells
 * NativeRenderPocActivity to overlay a real android.widget.VideoView
 * (with its built-in MediaController transport bar) at this exact rect,
 * the same "no DOM element to attach to, overlay a real Android View
 * instead" idiom TextField's EditText already uses.
 */
final class VideoPlayer implements Widget
{
    private readonly Widget $content;

    /**
     * @param ?string $label Set to null to show just the play icon, no
     *                       caption — every existing call site keeps
     *                       'Lire la vidéo' unless it opts out.
     */
    public function __construct(string $url, float $width, float $height = 200.0, ?string $label = 'Lire la vidéo')
    {
        $icon = new Icon('play_circle', 32.0, Tokens::ink()->toHex());

        // Real bug found testing this on a physical device: without an
        // explicit mainAxisAlignment, Flex::row defaults to START — once
        // it fills Center's bounded width (Flex fills any bounded
        // constraint, not just a tight one), the icon+label sat at the
        // row's own left edge instead of visually centered, the same
        // "hug vs fill" pitfall Button.php's own inner row already
        // guards against with the same fix. A bare icon (no label) skips
        // Flex::row entirely — Center() alone already centers it on both
        // axes, no row/mainAxisAlignment needed for a single child.
        $inner = $label === null ? $icon : Flex::row([
            $icon,
            new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), new Text($label, Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true)),
        ], mainAxisAlignment: MainAxisAlignment::CENTER, crossAxisAlignment: CrossAxisAlignment::CENTER);

        // "video-thumbnail:" prefix (not a real fetchable URL on its
        // own) tells NativeCanvasView.swift's image draw handler to
        // route this through VideoThumbnailLoader (AVAssetImageGenerator
        // pulling one real frame from the video itself, same "no
        // server-side asset needed" idea AVPlayer's own tap-to-play
        // already relies on for playback) instead of ImageLoader's plain
        // URLSession GET, which would just 404/fail to decode against an
        // .mp4 — same "url.hasPrefix(...)" branching precedent
        // ImageLoader.swift's own `data:` case already uses. Falls back
        // to the plain muted box underneath until the frame loads (or on
        // a platform/network that never produces one — Android hasn't
        // got its own VideoThumbnailLoader.kt counterpart yet).
        $box = new Container(
            new Stack([
                new Image("video-thumbnail:{$url}", $width, $height, radius: Tokens::RADIUS_LG),
                new Center($inner),
            ]),
            width: $width,
            height: $height,
            background: Tokens::surfaceMuted(),
            radius: Tokens::RADIUS_LG,
        );

        $this->content = new Tappable($box, "video:play:{$url}");
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
