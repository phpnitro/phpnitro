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
 * The native-tree equivalent of Engine\SelectBox — there's no HTML
 * <select> to fall back on, so tapping this field tells
 * NativeRenderPocActivity to show a real android.app.AlertDialog
 * single-choice list (the options travel in the hit region's meta, so no
 * second round-trip is needed to know what to offer). A pick is tracked
 * client-side exactly like NativeTextField's typed value — read by name
 * from $_GET on the next request, not pushed back synchronously.
 */
final class NativeSelectBox implements RenderNode
{
    private readonly RenderNode $content;

    /**
     * @param array<string, string> $options value => label
     */
    public function __construct(
        string $name,
        array $options,
        string $selected = '',
        string $placeholder = 'Choisir...',
        float $height = 52.0,
    ) {
        $displayText = $options[$selected] ?? $placeholder;
        $displayColor = isset($options[$selected]) ? Tokens::ink() : Tokens::inkMuted();

        $box = new RenderContainer(
            new RenderPadding(
                EdgeInsets::symmetric(horizontal: Tokens::SPACE_MD),
                new RenderCenter(RenderFlex::row([
                    new Flexible(new RenderText($displayText, Tokens::TEXT_BODY, $displayColor->toHex())),
                    new RenderIcon('expand_more', 20, Tokens::inkMuted()->toHex()),
                ], crossAxisAlignment: CrossAxisAlignment::CENTER)),
            ),
            height: $height,
            background: Tokens::surface(),
            radius: Tokens::RADIUS_MD,
            borderColor: Tokens::border(),
            borderWidth: 1.0,
        );

        $this->content = new RenderTappable($box, "select:{$name}", ['options' => $options, 'selected' => $selected]);
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
