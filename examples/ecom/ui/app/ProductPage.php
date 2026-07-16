<?php

namespace Engine\App;

use Backend\Repository\ProductRepository;
use Engine\AppBar;
use Engine\BottomNavigation;
use Engine\Button;
use Engine\Color;
use Engine\Column;
use Engine\FontWeight;
use Engine\Image;
use Engine\MapView;
use Engine\Scaffold;
use Engine\Screen;
use Engine\SingleScrollView;
use Engine\Text;
use Engine\TextSize;
use Engine\Widget;

final class ProductPage extends Screen
{
    protected function initialState(): array
    {
        return ['added' => false];
    }

    protected function onAddToCart(): void
    {
        Cart::add((int) $this->params['id']);
        $this->state['added'] = true;
    }

    public function build(): Widget
    {
        require_once dirname(__DIR__, 2) . '/backend/vendor/autoload.php';

        $product = (new ProductRepository())->find((int) $this->params['id']);

        if ($product === null) {
            return Scaffold::make(
                body: Text::make('Produit introuvable.'),
                appBar: AppBar::make('Produit', backHref: '/'),
            );
        }

        $price = number_format($product['price_cents'] / 100, 2, ',', ' ') . ' €';

        return Scaffold::make(
            body: SingleScrollView::make(Column::make([
                Image::make($product['image_url'], $product['name'], 'w-full aspect-square object-cover rounded-xl'),
                Text::make($product['name'], size: TextSize::XL2, weight: FontWeight::BOLD),
                Text::make($price, size: TextSize::XL, color: Color::blue(600), weight: FontWeight::BOLD),
                Text::make($product['description'], color: Color::gray(600)),
                Text::make(
                    $product['stock'] > 0 ? "{$product['stock']} en stock" : 'Rupture de stock',
                    color: $product['stock'] > 0 ? Color::green(600) : Color::red(600),
                ),
                Button::make(
                    $this->state['added'] ? 'Ajouté ✓' : 'Ajouter au panier',
                    action: 'addToCart',
                    classes: $this->state['added']
                        ? 'bg-green-600 text-white font-medium px-4 py-2 rounded-lg'
                        : 'bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg transition-colors',
                ),
                Text::make('Retrait en magasin', size: TextSize::LG, weight: FontWeight::SEMIBOLD),
                MapView::make(48.8566, 2.3522, zoom: 14),
            ], 'flex flex-col gap-3 p-4')),
            appBar: AppBar::make('Produit', backHref: '/'),
            bottomNavigation: BottomNavigation::make(AppNav::items()),
        );
    }
}
