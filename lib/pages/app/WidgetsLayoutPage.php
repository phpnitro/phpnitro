<?php

namespace Engine\App;

use Engine\Align;
use Engine\Alignment;
use Engine\Center;
use Engine\Color;
use Engine\Column;
use Engine\Container;
use Engine\Curves;
use Engine\Divider;
use Engine\FadeIn;
use Engine\Link;
use Engine\Margin;
use Engine\Padding;
use Engine\PageView;
use Engine\Positioned;
use Engine\Rounded;
use Engine\Row;
use Engine\Screen;
use Engine\SingleScrollView;
use Engine\Stack;
use Engine\Table;
use Engine\TableBorder;
use Engine\Text;
use Engine\Widget;
use Engine\Wrap;

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
                'Container — background/rounded typés (Color/Rounded), ajoutés par-dessus les classes brutes',
                Container::make(Text::make('typé', 'text-white'), 'h-16 flex items-center justify-center', background: Color::blue(600), rounded: Rounded::FULL),
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
                'FadeIn — animation d\'entrée au montage (fondu + léger glissement), pas de tween sur changement de propriété',
                Row::make([
                    FadeIn::make(
                        Container::make(Text::make('400ms ease-out', 'text-white'), 'p-3 bg-blue-600 rounded'),
                    ),
                    FadeIn::make(
                        Container::make(Text::make('délai 200ms', 'text-white'), 'p-3 bg-emerald-600 rounded'),
                        delayMs: 200,
                    ),
                    FadeIn::make(
                        Container::make(Text::make('overshoot', 'text-white'), 'p-3 bg-purple-600 rounded'),
                        curve: Curves::OVERSHOOT,
                    ),
                ], 'flex flex-row gap-3'),
            ),

            $this->section(
                'Stack / Positioned — superposition libre (badge en incrustation)',
                Stack::make([
                    Container::make(Text::make(''), 'h-24 bg-blue-600 rounded-lg'),
                    Positioned::make(
                        Container::make(Text::make('3', 'text-white text-xs font-bold'), 'px-1.5 py-0.5 bg-red-600 rounded-full'),
                        top: 4,
                        right: 4,
                    ),
                ]),
            ),

            $this->section(
                'Wrap — enfants qui passent à la ligne au lieu de déborder',
                Wrap::make([
                    Container::make(Text::make('tag-un', 'text-white'), 'px-3 py-1 bg-purple-600 rounded-full'),
                    Container::make(Text::make('tag-deux', 'text-white'), 'px-3 py-1 bg-purple-600 rounded-full'),
                    Container::make(Text::make('tag-trois', 'text-white'), 'px-3 py-1 bg-purple-600 rounded-full'),
                    Container::make(Text::make('tag-quatre', 'text-white'), 'px-3 py-1 bg-purple-600 rounded-full'),
                    Container::make(Text::make('tag-cinq', 'text-white'), 'px-3 py-1 bg-purple-600 rounded-full'),
                ]),
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
        ], 'flex flex-col gap-4 p-4'));
    }
}
