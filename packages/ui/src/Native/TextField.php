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
 * input is tracked client-side and only reaches PHP when a Button
 * with a "submit:" action collects every field's current value and sends
 * them along with that request.
 */
final class TextField implements Widget
{
    private readonly Widget $content;

    /**
     * @param bool $multiline The native-tree equivalent of Engine\Textarea — same
     *                        tap-to-overlay-a-real-EditText mechanism, just a taller
     *                        box, top-aligned text, and InputType.TYPE_TEXT_FLAG_MULTI_LINE
     *                        (see NativeRenderPocActivity.kt's showTextInput()).
     */
    public function __construct(
        string $name,
        string $value = '',
        string $placeholder = '',
        bool $obscure = false,
        bool $multiline = false,
        float $height = 52.0,
    ) {
        $resolvedHeight = $multiline ? max($height, 120.0) : $height;
        $hasValue = $value !== '';
        $displayText = $hasValue ? ($obscure ? str_repeat('•', mb_strlen($value)) : $value) : $placeholder;
        $displayColor = $hasValue ? Tokens::ink() : Tokens::inkMuted();

        $box = new Container(
            new Padding(
                $multiline
                    ? EdgeInsets::all(Tokens::SPACE_MD)
                    : EdgeInsets::only(left: Tokens::SPACE_MD, top: $resolvedHeight / 2 - Tokens::TEXT_BODY * 0.6),
                new Text($displayText, Tokens::TEXT_BODY, $displayColor->toHex()),
            ),
            height: $resolvedHeight,
            background: Tokens::surface(),
            radius: Tokens::RADIUS_MD,
            borderColor: Tokens::border(),
            borderWidth: 1.0,
        );

        $action = 'focus:' . ($multiline ? 'multiline:' : '') . ($obscure ? 'secure:' : '') . $name;
        $this->content = new Tappable($box, $action);
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
