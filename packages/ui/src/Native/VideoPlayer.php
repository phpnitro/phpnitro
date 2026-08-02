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
 * instead" idiom NativeTextField's EditText already uses.
 */
final class NativeVideoPlayer implements RenderNode
{
    private readonly RenderNode $content;

    public function __construct(string $url, float $width, float $height = 200.0)
    {
        $box = new RenderContainer(
            new RenderCenter(RenderFlex::row([
                new RenderIcon('play_circle', 32.0, Tokens::ink()->toHex()),
                new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_SM), new RenderText('Lire la vidéo', Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true)),
            ], crossAxisAlignment: CrossAxisAlignment::CENTER)),
            width: $width,
            height: $height,
            background: Tokens::surfaceMuted(),
            radius: Tokens::RADIUS_LG,
        );

        $this->content = new RenderTappable($box, "video:play:{$url}");
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->content->layout($constraints);
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $this->content->paint($canvas, $x, $y);
    }
}
