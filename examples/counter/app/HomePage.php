<?php

use Sdk\StatefulWidget;
use Sdk\Widget;
use Sdk\Column;
use Sdk\Text;
use Sdk\Button;

class HomePage extends StatefulWidget
{
    private int $count = 0;

    public function build(): Widget
    {
        return Column::new([
            Text::new('Compteur : ' . $this->count),
            Button::new('Incrémenter', onPressed: function () {
                $this->setState(function () {
                    $this->count = $this->count + 1;
                });
            }),
        ]);
    }
}
