<?php

namespace Engine\App;

use Engine\Column;
use Engine\Link;
use Engine\Screen;
use Engine\Text;
use Engine\Widget;

/**
 * Demonstrates route parameters: matched against '/product/{id}', the
 * captured value is available in $this->params['id'].
 */
final class ProductPage extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    public function build(): Widget
    {
        return Column::make([
            Text::make('Produit #' . $this->params['id'], 'text-2xl font-bold text-gray-900 dark:text-gray-100'),
            Link::make("Retour à l'accueil", '/'),
        ]);
    }
}
