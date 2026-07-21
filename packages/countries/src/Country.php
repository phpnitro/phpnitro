<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Countries;

/**
 * A single country's facts. Never constructed directly by consumers — get
 * instances from Countries::all()/find()/search()/byContinent().
 */
final class Country
{
    public function __construct(
        public readonly string $code,
        public readonly string $code3,
        public readonly string $name,
        public readonly string $nameFr,
        public readonly ?string $capital,
        public readonly Continent $continent,
        public readonly string $callingCode,
        public readonly string $currency,
    ) {
    }

    /**
     * Regional indicator symbols computed from the ISO code, not a stored
     * lookup table — always correct by construction (any two-letter code
     * maps to a flag the same way), nothing to keep in sync. Renders as an
     * emoji flag on any font/OS with regional indicator support (all
     * modern Android/iOS WebViews).
     */
    public function flag(): string
    {
        return mb_chr(0x1F1E6 + (ord($this->code[0]) - ord('A')))
            . mb_chr(0x1F1E6 + (ord($this->code[1]) - ord('A')));
    }

    /**
     * Up to 15 largest cities by population, largest first — always
     * includes the capital when GeoNames tracked it above the 15,000
     * population threshold this data was filtered at (see CityData). Falls
     * back to just the capital when the country has no separate city data
     * (a handful of small island nations aren't in the GeoNames extract).
     *
     * @return array<int, string>
     */
    public function cities(): array
    {
        $cities = CityData::byCountry()[$this->code] ?? [];

        if ($cities === [] && $this->capital !== null) {
            return [$this->capital];
        }

        return $cities;
    }
}
