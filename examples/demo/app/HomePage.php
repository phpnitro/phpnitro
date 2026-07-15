<?php

use Sdk\StatelessWidget;
use Sdk\Widget;
use Sdk\Column;
use Sdk\Text;
use Sdk\Button;

class HomePage extends StatelessWidget
{
    public function build(): Widget
    {
        return Column::new([
            Text::new('Mon application'),
            Button::new('Connexion'),
        ]);
    }
}
