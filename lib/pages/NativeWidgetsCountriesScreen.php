<?php

namespace Engine\App;

use Engine\Countries\Continent;
use Engine\Countries\Countries;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\AppBar;
use Engine\Native\Divider;
use Engine\Native\Scaffold;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * The native conversion of WidgetsCountriesPage.php — Engine\Countries\
 * is pure PHP data (no network, no API key), so this is a plain read +
 * Text composition, nothing device-specific to port.
 */
final class NativeWidgetsCountriesScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        $france = Countries::find('FR');
        $benin = Countries::find('BJ');

        $caption = static fn (string $text): Widget => new Padding(
            EdgeInsets::only(top: Tokens::SPACE_LG, bottom: Tokens::SPACE_SM),
            new Text($text, Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex(), bold: true, letterSpacing: 0.04),
        );

        $countryLine = static fn ($country): Widget => new Padding(
            EdgeInsets::only(top: Tokens::SPACE_SM),
            Flex::row([
                new Text($country->flag(), Tokens::TEXT_DISPLAY - 4, Tokens::ink()->toHex()),
                new Padding(
                    EdgeInsets::only(left: Tokens::SPACE_SM),
                    new Text("{$country->nameFr} — {$country->capital} — {$country->currency} — {$country->callingCode}", Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),
                ),
            ], crossAxisAlignment: CrossAxisAlignment::CENTER),
        );

        $searchResults = array_map(
            static fn ($c): Widget => new Padding(EdgeInsets::only(top: 4), new Text("{$c->flag()} {$c->nameFr}", Tokens::TEXT_BODY_SMALL, Tokens::ink()->toHex())),
            Countries::search('stan'),
        );

        $body = new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    $caption("Country::find() — code, drapeau, capitale, devise"),
                    $countryLine($france),
                    $countryLine($benin),

                    $caption("Country::cities() — jusqu'à 15 plus grandes villes par pays"),
                    new Text(implode(', ', $france->cities()), Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),

                    $caption('Countries::byContinent() — filtre par continent'),
                    new Text(count(Countries::byContinent(Continent::SOUTH_AMERICA)) . ' pays en ' . Continent::SOUTH_AMERICA->label(), Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),

                    $caption("Countries::search() — recherche FR/EN insensible à la casse"),
                    Flex::column($searchResults),

                    new Divider(),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_SM),
                        new Text('Données : mledoze/countries (ODbL) + GeoNames cities15000 (CC BY 4.0).', Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex()),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new Scaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new AppBar($screenWidth, 'Countries', backAction: 'back'),
        );
    }
}
