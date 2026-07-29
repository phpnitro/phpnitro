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
 * A tappable field that opens a real native keyboard — there's no DOM
 * `<input>` for the OS's IME to attach to on a Canvas, so tapping this
 * box tells NativeRenderPocActivity to overlay a real android.widget.
 * EditText at this exact rect (see its showTextInput()), positioned via
 * the same dp coordinates every draw command already uses. The value
 * shown here (before focus) is whatever PHP was last told about — typed
 * input is tracked client-side and only reaches PHP when a NativeButton
 * with a "submit:" action collects every field's current value and sends
 * them along with that request.
 */
final class NativeTextField implements RenderNode
{
    private readonly RenderNode $content;

    public function __construct(
        string $name,
        string $value = '',
        string $placeholder = '',
        bool $obscure = false,
        float $height = 52.0,
    ) {
        $hasValue = $value !== '';
        $displayText = $hasValue ? ($obscure ? str_repeat('•', mb_strlen($value)) : $value) : $placeholder;
        $displayColor = $hasValue ? Tokens::ink() : Tokens::inkMuted();

        $box = new RenderContainer(
            new RenderPadding(
                EdgeInsets::only(left: Tokens::SPACE_MD, top: $height / 2 - Tokens::TEXT_BODY * 0.6),
                new RenderText($displayText, Tokens::TEXT_BODY, $displayColor->toHex()),
            ),
            height: $height,
            background: Tokens::surface(),
            radius: Tokens::RADIUS_MD,
            borderColor: Tokens::border(),
            borderWidth: 1.0,
        );

        $action = 'focus:' . ($obscure ? 'secure:' : '') . $name;
        $this->content = new RenderTappable($box, $action);
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
