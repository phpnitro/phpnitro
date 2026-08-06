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
 * A country picker — a SelectBox pre-filled from Engine\Countries's
 * already-bundled offline dataset (flag computed from the ISO code, see
 * Country::flag()), no separate country list of its own to keep in sync.
 * The stored value is the ISO 3166-1 alpha-2 code ("FR"), not the display
 * label — look it up again via Countries::find() wherever the full
 * Country (name, currency, calling code...) is needed.
 */
final class CountryPicker implements Widget
{
    private readonly Widget $content;

    public function __construct(
        string $name,
        string $selected = '',
        string $placeholder = 'Choisir un pays...',
        bool $french = false,
    ) {
        $options = [];
        foreach (Countries::all() as $country) {
            $options[$country->code] = $country->flag() . ' ' . ($french ? $country->nameFr : $country->name);
        }
        asort($options);

        $this->content = new SelectBox($name, $options, $selected, $placeholder);
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
