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
 * A phone number field with an attached country dial-code picker —
 * CountryCodePicker + TextField side by side, two independently tracked
 * $_GET values (the ISO code and the typed digits), not one combined
 * string. Combine them yourself where needed: the digits plus
 * CountryCodePicker::dialCode($isoCode) is the full E.164-ish number.
 */
final class IntlPhoneNumberInput implements Widget
{
    private readonly Widget $content;

    public function __construct(
        string $countryFieldName,
        string $phoneFieldName,
        string $selectedCountry = '',
        string $phoneValue = '',
        string $placeholder = 'Numéro de téléphone',
        ?string $error = null,
    ) {
        $this->content = Flex::row([
            new CountryCodePicker($countryFieldName, $selectedCountry),
            new Flexible(new Padding(
                EdgeInsets::only(left: Tokens::SPACE_SM),
                new TextField($phoneFieldName, $phoneValue, $placeholder, error: $error),
            )),
        ], crossAxisAlignment: CrossAxisAlignment::START);
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
