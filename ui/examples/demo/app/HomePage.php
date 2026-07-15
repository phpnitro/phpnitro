<?php

use Sdk\StatelessWidget;
use Sdk\Widget;
use Sdk\Column;
use Sdk\Text;
use Sdk\Button;
use Sdk\Container;
use Sdk\SizedBox;
use Sdk\Env;

class HomePage extends StatelessWidget
{
    public function build(): Widget
    {
        return Column::new([
            Container::new(Text::new(Env::get('APP_NAME')), color: '#2196F3'),
            SizedBox::new(height: 16.0),
            Button::new('Connexion'),
        ]);
    }
}
