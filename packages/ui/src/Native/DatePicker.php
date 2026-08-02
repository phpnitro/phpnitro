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
 * The native-tree equivalent of Engine\DatePicker — that HTML widget was
 * already a thin wrapper (Android's WebView delegates input[type=date] to
 * the OS date-picker dialog on its own), so this is the more direct path:
 * tapping the field tells NativeRenderPocActivity to show a real
 * android.app.DatePickerDialog, same dialog either pipeline ends up at.
 * The picked value comes back as an ISO "YYYY-MM-DD" string, same shape
 * DatePicker.php's HTML input already produced.
 */
final class NativeDatePicker implements RenderNode
{
    private readonly RenderNode $content;

    public function __construct(
        string $name,
        string $value = '',
        string $placeholder = 'jj/mm/aaaa',
        float $height = 52.0,
    ) {
        $displayText = $value !== '' ? $value : $placeholder;
        $displayColor = $value !== '' ? Tokens::ink() : Tokens::inkMuted();

        $box = new RenderContainer(
            new RenderPadding(
                EdgeInsets::symmetric(horizontal: Tokens::SPACE_MD),
                new RenderCenter(RenderFlex::row([
                    new Flexible(new RenderText($displayText, Tokens::TEXT_BODY, $displayColor->toHex())),
                    new RenderIcon('calendar_today', 18, Tokens::inkMuted()->toHex()),
                ], crossAxisAlignment: CrossAxisAlignment::CENTER)),
            ),
            height: $height,
            background: Tokens::surface(),
            radius: Tokens::RADIUS_MD,
            borderColor: Tokens::border(),
            borderWidth: 1.0,
        );

        $this->content = new RenderTappable($box, "datepicker:{$name}", ['value' => $value]);
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
