<?php

namespace Engine\App;

use Engine\Countries\Continent;
use Engine\Countries\Countries;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeDivider;
use Engine\Native\NativeScaffold;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * The native conversion of WidgetsCountriesPage.php — Engine\Countries\
 * is pure PHP data (no network, no API key), so this is a plain read +
 * RenderText composition, nothing device-specific to port.
 */
final class NativeWidgetsCountriesScreen
{
    public static function build(float $screenWidth, float $screenHeight): RenderNode
    {
        $france = Countries::find('FR');
        $benin = Countries::find('BJ');

        $caption = static fn (string $text): RenderNode => new RenderPadding(
            EdgeInsets::only(top: Tokens::SPACE_LG, bottom: Tokens::SPACE_SM),
            new RenderText($text, Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex(), bold: true, letterSpacing: 0.04),
        );

        $countryLine = static fn ($country): RenderNode => new RenderPadding(
            EdgeInsets::only(top: Tokens::SPACE_SM),
            RenderFlex::row([
                new RenderText($country->flag(), Tokens::TEXT_DISPLAY - 4, Tokens::ink()->toHex()),
                new RenderPadding(
                    EdgeInsets::only(left: Tokens::SPACE_SM),
                    new RenderText("{$country->nameFr} — {$country->capital} — {$country->currency} — {$country->callingCode}", Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),
                ),
            ], crossAxisAlignment: CrossAxisAlignment::CENTER),
        );

        $searchResults = array_map(
            static fn ($c): RenderNode => new RenderPadding(EdgeInsets::only(top: 4), new RenderText("{$c->flag()} {$c->nameFr}", Tokens::TEXT_BODY_SMALL, Tokens::ink()->toHex())),
            Countries::search('stan'),
        );

        $body = new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    $caption("Country::find() — code, drapeau, capitale, devise"),
                    $countryLine($france),
                    $countryLine($benin),

                    $caption("Country::cities() — jusqu'à 15 plus grandes villes par pays"),
                    new RenderText(implode(', ', $france->cities()), Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),

                    $caption('Countries::byContinent() — filtre par continent'),
                    new RenderText(count(Countries::byContinent(Continent::SOUTH_AMERICA)) . ' pays en ' . Continent::SOUTH_AMERICA->label(), Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),

                    $caption("Countries::search() — recherche FR/EN insensible à la casse"),
                    RenderFlex::column($searchResults),

                    new NativeDivider(),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_SM),
                        new RenderText('Données : mledoze/countries (ODbL) + GeoNames cities15000 (CC BY 4.0).', Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex()),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new NativeScaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new NativeAppBar($screenWidth, 'Countries', backAction: 'back'),
        );
    }
}
