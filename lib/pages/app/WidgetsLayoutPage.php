<?php

namespace Engine\App;

use Engine\Align;
use Engine\Alignment;
use Engine\BottomNavigation;
use Engine\Center;
use Engine\Column;
use Engine\Container;
use Engine\Divider;
use Engine\Link;
use Engine\Margin;
use Engine\Padding;
use Engine\PageView;
use Engine\Row;
use Engine\Screen;
use Engine\SingleScrollView;
use Engine\Table;
use Engine\TableBorder;
use Engine\Text;
use Engine\Widget;

final class WidgetsLayoutPage extends Screen
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
        return SingleScrollView::make(Column::make([
            Text::make('Mise en page', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),

            $this->section(
                'Align — un enfant positionné dans une zone plus grande',
                Container::make(
                    Align::make(Text::make('coin', 'text-white'), Alignment::BOTTOM_RIGHT),
                    'h-24 bg-blue-600 rounded-lg',
                ),
            ),

            $this->section(
                'Center — centre un seul enfant',
                Container::make(
                    Center::make(Text::make('centré', 'text-white')),
                    'h-24 bg-blue-600 rounded-lg',
                ),
            ),

            $this->section(
                'Container — padding + fond + coins arrondis',
                Container::make(Text::make('Contenu', 'text-gray-900 dark:text-gray-100'), 'p-6 bg-gray-100 dark:bg-gray-800 rounded-xl'),
            ),

            $this->section(
                'Row — enfants alignés horizontalement',
                Row::make([
                    Container::make(Text::make('1', 'text-white'), 'p-3 bg-blue-600 rounded'),
                    Container::make(Text::make('2', 'text-white'), 'p-3 bg-blue-600 rounded'),
                    Container::make(Text::make('3', 'text-white'), 'p-3 bg-blue-600 rounded'),
                ]),
            ),

            $this->section(
                'Margin / Padding — espacement autour ou dedans',
                Container::make(
                    Margin::make(Padding::make(Text::make('espacé', 'text-white'), 'p-3 bg-blue-600 rounded'), 'm-3'),
                    'bg-gray-100 dark:bg-gray-800 rounded-xl',
                ),
            ),

            $this->section(
                'Table',
                Table::make(
                    rows: [['Casque sans fil', '89,90 €'], ['Montre connectée', '149,00 €']],
                    headers: ['Produit', 'Prix'],
                    border: TableBorder::ALL,
                ),
            ),

            $this->section(
                'PageView — pages qui se snappent au scroll horizontal',
                PageView::make([
                    Container::make(Text::make('Page A', 'text-white'), 'h-24 bg-blue-600 rounded-lg flex items-center justify-center'),
                    Container::make(Text::make('Page B', 'text-white'), 'h-24 bg-emerald-600 rounded-lg flex items-center justify-center'),
                    Container::make(Text::make('Page C', 'text-white'), 'h-24 bg-purple-600 rounded-lg flex items-center justify-center'),
                ]),
            ),

            Link::make('Retour à la vitrine', '/widgets'),
            BottomNavigation::make(AppNav::items(), variant: BottomNavigation::VARIANT_PILLS),
        ], 'flex flex-col gap-4 p-4'));
    }
}
