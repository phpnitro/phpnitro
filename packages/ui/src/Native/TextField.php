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
    /**
     * @param ?float $width Explicit width, bypassing the usual reliance on
     *                      a STRETCH-aligned Flex ancestor — needed by
     *                      PasswordField, which wraps this in a Stack
     *                      (Stack always loosens the constraint it hands
     *                      non-Positioned children, so an ordinary
     *                      unconstrained TextField would shrink to its
     *                      own content width instead of filling the row).
     *                      Every existing call site omits this and keeps
     *                      relying on STRETCH exactly as before.
     */
    /**
     * @param ?string $error Set by a Validator failure (see Engine\Validation\Validator)
     *                       for this field's name — renders a red border on the box plus
     *                       a caption line below it, the same round-trip-driven pattern
     *                       every other stateful widget here already uses (PHP decides
     *                       what to render, the client has no validation logic of its
     *                       own). Purely visual: nothing here blocks the "submit:" Button
     *                       from being tapped again, PHP re-validates on every submit
     *                       the same way it always has.
     */
    public function __construct(
        string $name,
        string $value = '',
        string $placeholder = '',
        bool $obscure = false,
        bool $multiline = false,
        float $height = 52.0,
        ?float $width = null,
        ?string $error = null,
    ) {
        $resolvedHeight = $multiline ? max($height, 120.0) : $height;
        $hasValue = $value !== '';
        $displayText = $hasValue ? ($obscure ? str_repeat('•', mb_strlen($value)) : $value) : $placeholder;
        $displayColor = $hasValue ? Tokens::ink() : Tokens::inkMuted();
        $hasError = $error !== null && $error !== '';

        $box = new Container(
            new Padding(
                $multiline
                    ? EdgeInsets::all(Tokens::SPACE_MD)
                    : EdgeInsets::only(left: Tokens::SPACE_MD, top: $resolvedHeight / 2 - Tokens::TEXT_BODY * 0.6),
                new Text($displayText, Tokens::TEXT_BODY, $displayColor->toHex()),
            ),
            width: $width,
            height: $resolvedHeight,
            background: Tokens::surface(),
            radius: Tokens::RADIUS_MD,
            borderColor: $hasError ? Tokens::danger() : Tokens::border(),
            borderWidth: $hasError ? 1.5 : 1.0,
        );

        $action = 'focus:' . ($multiline ? 'multiline:' : '') . ($obscure ? 'secure:' : '') . $name;
        $tappableBox = new Tappable($box, $action);

        $this->content = $hasError
            ? Flex::column([
                $tappableBox,
                new Padding(
                    EdgeInsets::only(top: 4.0, left: Tokens::SPACE_XS),
                    new Text($error, Tokens::TEXT_BODY_SMALL, Tokens::danger()->toHex()),
                ),
            ])
            : $tappableBox;
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
