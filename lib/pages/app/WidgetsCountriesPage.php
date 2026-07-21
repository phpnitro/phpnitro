<?php

namespace Engine\App;

use Engine\Column;
use Engine\Container;
use Engine\Countries\Continent;
use Engine\Countries\Countries;
use Engine\Divider;
use Engine\Link;
use Engine\Row;
use Engine\Screen;
use Engine\SingleScrollView;
use Engine\Text;
use Engine\Widget;

/**
 * Engine\Countries\ — offline country/city data (194 UN member/independent
 * states), no network call, no API key. See
 * packages/countries/DATA_LICENSE.md for where the data comes from.
 */
final class WidgetsCountriesPage extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    private function section(string $caption, Widget $example): Widget
    {
        return Column::make([
            Text::make($caption, 'text-sm text-gray-500 dark:text-gray-400'),
            $example,
            Divider::make(),
        ], 'flex flex-col gap-2');
    }

    public function build(): Widget
    {
        $france = Countries::find('FR');
        $benin = Countries::find('BJ');

        return SingleScrollView::make(Column::make([
            Text::make('Countries (Engine\\Countries\\)', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),

            $this->section(
                'Country::find() — code, drapeau (calculé, pas stocké), capitale, devise',
                Column::make([
                    Row::make([
                        Text::make($france->flag(), 'text-3xl'),
                        Text::make("{$france->nameFr} — {$france->capital} — {$france->currency} — {$france->callingCode}"),
                    ]),
                    Row::make([
                        Text::make($benin->flag(), 'text-3xl'),
                        Text::make("{$benin->nameFr} — {$benin->capital} — {$benin->currency} — {$benin->callingCode}"),
                    ]),
                ], 'flex flex-col gap-2'),
            ),

            $this->section(
                'Country::cities() — jusqu\'à 15 plus grandes villes par pays',
                Text::make(implode(', ', $france->cities()), 'text-sm'),
            ),

            $this->section(
                'Countries::byContinent() — filtre par continent',
                Text::make(
                    count(Countries::byContinent(Continent::SOUTH_AMERICA)) . ' pays en ' . Continent::SOUTH_AMERICA->label(),
                ),
            ),

            $this->section(
                'Countries::search() — recherche FR/EN insensible à la casse',
                Column::make(
                    array_map(
                        static fn ($c) => Text::make("{$c->flag()} {$c->nameFr}"),
                        Countries::search('stan'),
                    ),
                    'flex flex-col gap-1',
                ),
            ),

            Container::make(
                Text::make(
                    'Données : mledoze/countries (ODbL) + GeoNames cities15000 (CC BY 4.0) — voir DATA_LICENSE.md.',
                    'text-xs text-gray-400 dark:text-gray-500',
                ),
            ),

            Link::make('Retour à la vitrine', '/widgets'),
        ], 'flex flex-col gap-4 p-4'));
    }
}
