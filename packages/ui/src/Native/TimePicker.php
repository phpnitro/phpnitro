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
 * The native-tree equivalent of Engine\TimePicker — same reasoning as
 * NativeDatePicker: tapping the field opens a real
 * android.app.TimePickerDialog. The picked value comes back as "HH:mm",
 * same shape TimePicker.php's HTML input[type=time] already produced.
 */
final class NativeTimePicker implements RenderNode
{
    private readonly RenderNode $content;

    public function __construct(
        string $name,
        string $value = '',
        string $placeholder = '--:--',
        float $height = 52.0,
    ) {
        $displayText = $value !== '' ? $value : $placeholder;
        $displayColor = $value !== '' ? Tokens::ink() : Tokens::inkMuted();

        $box = new RenderContainer(
            new RenderPadding(
                EdgeInsets::symmetric(horizontal: Tokens::SPACE_MD),
                new RenderCenter(RenderFlex::row([
                    new Flexible(new RenderText($displayText, Tokens::TEXT_BODY, $displayColor->toHex())),
                    new RenderIcon('schedule', 18, Tokens::inkMuted()->toHex()),
                ], crossAxisAlignment: CrossAxisAlignment::CENTER)),
            ),
            height: $height,
            background: Tokens::surface(),
            radius: Tokens::RADIUS_MD,
            borderColor: Tokens::border(),
            borderWidth: 1.0,
        );

        $this->content = new RenderTappable($box, "timepicker:{$name}", ['value' => $value]);
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
