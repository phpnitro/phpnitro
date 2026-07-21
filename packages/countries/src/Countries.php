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
 * Offline world country/city data — 194 UN member/independent states, no
 * network call, no API key (see packages/countries/DATA_LICENSE for the
 * two open datasets this is built from). Flutter's countries-style
 * packages usually wrap a remote API or a bundled JSON asset parsed at
 * runtime; here the data is plain PHP arrays (CountryData/CityData),
 * already in the exact shape autoloading needs — no parse step, no asset
 * file to read from disk.
 */
final class Countries
{
    /**
     * @var array<int, Country>|null
     */
    private static ?array $cache = null;

    /**
     * @return array<int, Country>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        return self::$cache = array_map(
            static fn (array $row) => new Country(
                $row['cca2'],
                $row['cca3'],
                $row['name'],
                $row['nameFr'],
                $row['capital'],
                Continent::from($row['continent']),
                $row['callingCode'],
                $row['currency'],
            ),
            CountryData::all(),
        );
    }

    /**
     * Accepts either the ISO 3166-1 alpha-2 ("FR") or alpha-3 ("FRA") code,
     * case-insensitively.
     */
    public static function find(string $code): ?Country
    {
        $code = strtoupper($code);

        foreach (self::all() as $country) {
            if ($country->code === $code || $country->code3 === $code) {
                return $country;
            }
        }

        return null;
    }

    /**
     * Case-insensitive substring match against both the English and French
     * common names.
     *
     * @return array<int, Country>
     */
    public static function search(string $query): array
    {
        $query = mb_strtolower(trim($query));

        if ($query === '') {
            return [];
        }

        return array_values(array_filter(
            self::all(),
            static fn (Country $c) => str_contains(mb_strtolower($c->name), $query)
                || str_contains(mb_strtolower($c->nameFr), $query),
        ));
    }

    /**
     * @return array<int, Country>
     */
    public static function byContinent(Continent $continent): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (Country $c) => $c->continent === $continent,
        ));
    }
}
