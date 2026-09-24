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

    public function __construct(string $url, float $width, float $height = 200.0)
    {
        // Real bug found testing this on a physical device: without an
        // explicit mainAxisAlignment, Flex::row defaults to START — once
        // it fills Center's bounded width (Flex fills any bounded
        // constraint, not just a tight one), the icon+label sat at the
        // row's own left edge instead of visually centered, the same
        // "hug vs fill" pitfall Button.php's own inner row already
        // guards against with the same fix.
        $box = new Container(
            new Center(Flex::row([
                new Icon('play_circle', 32.0, Tokens::ink()->toHex()),
                new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), new Text('Lire la vidéo', Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true)),
            ], mainAxisAlignment: MainAxisAlignment::CENTER, crossAxisAlignment: CrossAxisAlignment::CENTER)),
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
