<?php

namespace Engine\App;

use Engine\Button;
use Engine\Column;
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
            // '/' has no WebView route anymore — HomePage.php was removed
            // once NativeHomeScreen.php reached full parity (see git
            // history) — this opens the native screen that replaced it.
            Button::make("Retour à l'accueil", onClick: "phpxDevice.openNativeRenderPreviewAt('home')", classes: 'text-blue-600 hover:underline text-left'),
        ]);
    }
}
