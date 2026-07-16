<?php

namespace Engine\App;

use Backend\Repository\OrderRepository;
use Backend\Repository\ProductRepository;
use Engine\AppBar;
use Engine\Button;
use Engine\Checkbox;
use Engine\Color;
use Engine\Column;
use Engine\FingerprintButton;
use Engine\Form;
use Engine\Link;
use Engine\Scaffold;
use Engine\Screen;
use Engine\SelectBox;
use Engine\Text;
use Engine\TextField;
use Engine\Widget;

final class CheckoutPage extends Screen
{
    protected function initialState(): array
    {
        return ['error' => null];
    }

    /**
     * @param array<string, string> $data
     */
    protected function onConfirm(array $data): ?string
    {
        $cartItems = Cart::items();

        if ($cartItems === []) {
            $this->state['error'] = 'Ton panier est vide.';

            return null;
        }

        if (trim($data['name'] ?? '') === '' || trim($data['address'] ?? '') === '') {
            $this->state['error'] = 'Nom et adresse sont obligatoires.';

            return null;
        }

        if (($data['terms'] ?? null) !== '1') {
            $this->state['error'] = "Tu dois accepter les conditions générales.";

            return null;
        }

        $products = new ProductRepository();
        $items = [];
        $totalCents = 0;

        foreach ($cartItems as $productId => $quantity) {
            $product = $products->find($productId);
            if ($product === null) {
                continue;
            }

            $items[] = ['name' => $product['name'], 'quantity' => $quantity, 'price_cents' => $product['price_cents']];
            $totalCents += $product['price_cents'] * $quantity;
        }

        $orderId = (new OrderRepository())->create($data['name'], $data['address'], $items, $totalCents);
        Cart::clear();

        return "/order/{$orderId}";
    }

    public function build(): Widget
    {
        $children = [];

        if ($this->state['error'] !== null) {
            $children[] = Text::make($this->state['error'], color: Color::red(600));
        }

        $children[] = Form::make([
            TextField::make('name', label: 'Nom complet'),
            TextField::make('address', label: 'Adresse de livraison'),
            SelectBox::make('delivery', [
                'standard' => 'Standard (3-5 jours)',
                'express' => 'Express (24h)',
            ], selected: 'standard', label: 'Mode de livraison'),
            Checkbox::make('terms', "J'accepte les conditions générales"),
            FingerprintButton::make('Confirmer avec biométrie'),
            Button::make('Valider la commande', classes: 'bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg w-full'),
        ], action: 'confirm');

        $children[] = Link::make('Retour au panier', '/cart');

        return Scaffold::make(
            body: Column::make($children, 'flex flex-col gap-4 p-4'),
            appBar: AppBar::make('Paiement', backHref: '/cart'),
        );
    }
}
