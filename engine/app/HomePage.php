<?php

namespace Engine\App;

use Engine\Button;
use Engine\Column;
use Engine\Text;
use Engine\Widget;

final class HomePage
{
    public function build(): Widget
    {
        return Column::make([
            Text::make('Mon application', 'text-2xl font-bold text-gray-900'),
            Text::make('Servi en direct par PHP, stylé avec Tailwind.'),
            Button::make('Connexion'),
        ]);
    }
}
