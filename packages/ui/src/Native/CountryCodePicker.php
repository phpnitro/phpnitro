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

use Engine\Countries\Countries;

/**
 * A compact dial-code picker, meant to sit beside a phone TextField (see
 * IntlPhoneNumberInput) — same Engine\Countries dataset as CountryPicker,
 * just a narrower box showing flag + calling code instead of flag + name.
 *
 * The stored value is still the ISO code, NOT the calling code —
 * multiple countries share the same calling code (+1 covers the US,
 * Canada, and a dozen Caribbean nations), so the calling code alone
 * can't round-trip back to a specific country. Call
 * CountryCodePicker::dialCode($isoCode) to resolve the actual "+NN"
 * string once a screen has a selection.
 */
final class CountryCodePicker implements Widget
{
    private readonly Widget $content;

    public function __construct(
        string $name,
        string $selected = '',
        float $width = 110.0,
    ) {
        $options = [];
        foreach (Countries::all() as $country) {
            $options[$country->code] = $country->flag() . ' ' . $country->callingCode;
        }

        $displayText = $options[$selected] ?? '🌐 +__';

        $box = new Container(
            new Padding(
                EdgeInsets::symmetric(horizontal: Tokens::SPACE_SM),
                new Center(Flex::row([
                    new Flexible(new Text($displayText, Tokens::TEXT_BODY, Tokens::ink()->toHex())),
                    new Icon('expand_more', 18, Tokens::inkMuted()->toHex()),
                ], crossAxisAlignment: CrossAxisAlignment::CENTER)),
            ),
            width: $width,
            height: 52.0,
            background: Tokens::surface(),
            radius: Tokens::RADIUS_MD,
            borderColor: Tokens::border(),
            borderWidth: 1.0,
        );

        $this->content = new Tappable($box, "select:{$name}", ['options' => $options, 'selected' => $selected]);
    }

    public static function dialCode(string $isoCode): ?string
    {
        return Countries::find($isoCode)?->callingCode;
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
