<?php

namespace Engine\App;

use Backend\Repository\ProductRepository;
use Engine\AppBar;
use Engine\BottomNavigation;
use Engine\Button;
use Engine\Color;
use Engine\Column;
use Engine\FontWeight;
use Engine\Form;
use Engine\Image;
use Engine\Link;
use Engine\ListView;
use Engine\Row;
use Engine\Scaffold;
use Engine\Screen;
use Engine\Text;
use Engine\TextField;
use Engine\TextSize;
use Engine\Widget;

final class CartPage extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    /**
     * @param array<string, string> $data
     */
    protected function onRemoveItem(array $data): void
    {
        Cart::remove((int) $data['product_id']);
    }

    public function build(): Widget
    {
        $repository = new ProductRepository();
        $cartItems = Cart::items();

        if ($cartItems === []) {
            return Scaffold::make(
                body: Column::make([
                    Text::make('Ton panier est vide.', color: Color::gray(600)),
                    Link::make('Voir la boutique', '/'),
                ], 'flex flex-col gap-3 p-4'),
                appBar: AppBar::make('Panier'),
                bottomNavigation: BottomNavigation::make(AppNav::items()),
            );
        }

        $rows = [];
        $totalCents = 0;

        foreach ($cartItems as $productId => $quantity) {
            $product = $repository->find($productId);
            if ($product === null) {
                continue;
            }

            $lineCents = $product['price_cents'] * $quantity;
            $totalCents += $lineCents;

            $rows[] = Row::make([
                Image::make($product['image_url'], $product['name'], 'w-16 h-16 object-cover rounded-lg'),
                Column::make([
                    Text::make($product['name'], weight: FontWeight::MEDIUM),
                    Text::make("Qté : {$quantity} — " . number_format($lineCents / 100, 2, ',', ' ') . ' €', color: Color::gray(600)),
                ], 'flex flex-col gap-0.5 flex-1'),
                Form::make([
                    TextField::make('product_id', value: (string) $productId, type: 'hidden'),
                    Button::make('Retirer', classes: 'text-red-600 hover:underline text-sm'),
                ], action: 'removeItem'),
            ], 'items-center gap-3');
        }

        return Scaffold::make(
            body: Column::make([
                ListView::make($rows),
                Text::make('Total : ' . number_format($totalCents / 100, 2, ',', ' ') . ' €', size: TextSize::XL, weight: FontWeight::BOLD),
                Link::make('Passer commande', '/checkout', 'block text-center bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg'),
            ], 'flex flex-col gap-4 p-4'),
            appBar: AppBar::make('Panier'),
            bottomNavigation: BottomNavigation::make(AppNav::items()),
        );
    }
}
