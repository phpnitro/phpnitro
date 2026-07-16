<?php

namespace Engine\App;

use Backend\Repository\ProductRepository;
use Engine\AppBar;
use Engine\BottomNavigation;
use Engine\Color;
use Engine\Column;
use Engine\FontWeight;
use Engine\Image;
use Engine\LinkWrap;
use Engine\Scaffold;
use Engine\Screen;
use Engine\Text;
use Engine\Widget;

final class HomePage extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    public function build(): Widget
    {
        $products = (new ProductRepository())->findAll();

        $cards = array_map(
            fn (array $product) => $this->productCard($product),
            $products,
        );

        return Scaffold::make(
            body: Column::make($cards, 'grid grid-cols-2 gap-3'),
            appBar: AppBar::make('Ma Boutique'),
            bottomNavigation: BottomNavigation::make(AppNav::items()),
        );
    }

    /**
     * @param array<string, mixed> $product
     */
    private function productCard(array $product): Widget
    {
        $price = number_format($product['price_cents'] / 100, 2, ',', ' ') . ' €';

        return LinkWrap::make(
            Column::make([
                Image::make($product['image_url'], $product['name'], 'w-full aspect-square object-cover rounded-t-xl'),
                Column::make([
                    Text::make($product['name'], weight: FontWeight::MEDIUM),
                    Text::make($price, color: Color::blue(600), weight: FontWeight::BOLD),
                ], 'flex flex-col gap-0.5 p-2'),
            ], 'bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden'),
            href: "/product/{$product['id']}",
        );
    }
}
