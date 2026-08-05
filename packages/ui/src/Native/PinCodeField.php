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
 * An OTP-style code entry — visually N individual boxes, but backed by
 * exactly one real text field underneath (there's no way to route
 * per-character IME input across N separate EditText overlays without
 * real cursor/focus-advance logic NativeCanvasView.kt doesn't have).
 * Tapping any box focuses the same underlying field as TextField's own
 * "focus:$name" mechanism — the OS keyboard shows once, backspace/typing
 * behaves exactly like a normal text field, this widget only changes how
 * the current value is DRAWN (split across boxes instead of one line).
 */
final class PinCodeField implements Widget
{
    private readonly Widget $content;

    public function __construct(
        string $name,
        string $value = '',
        int $length = 4,
        float $boxSize = 48.0,
        ?string $error = null,
    ) {
        $chars = mb_str_split($value);
        $hasError = $error !== null && $error !== '';

        $boxes = [];
        for ($i = 0; $i < $length; $i++) {
            $char = $chars[$i] ?? '';
            $filled = $char !== '';
            $boxes[] = new Padding(
                EdgeInsets::only(left: $i > 0 ? Tokens::SPACE_SM : 0),
                new Container(
                    new Center(new Text($char, Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true)),
                    width: $boxSize,
                    height: $boxSize,
                    background: Tokens::surface(),
                    radius: Tokens::RADIUS_MD,
                    borderColor: $hasError ? Tokens::danger() : ($filled ? Tokens::ink() : Tokens::border()),
                    borderWidth: $filled || $hasError ? 1.5 : 1.0,
                ),
            );
        }

        $row = new Tappable(Flex::row($boxes), "focus:{$name}");

        $this->content = $hasError
            ? Flex::column([
                $row,
                new Padding(
                    EdgeInsets::only(top: 4.0, left: Tokens::SPACE_XS),
                    new Text($error, Tokens::TEXT_BODY_SMALL, Tokens::danger()->toHex()),
                ),
            ])
            : $row;
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
