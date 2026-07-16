<?php

namespace Engine\App;

use Backend\Repository\OrderRepository;
use Engine\AppBar;
use Engine\Color;
use Engine\Column;
use Engine\FontWeight;
use Engine\Link;
use Engine\Scaffold;
use Engine\Screen;
use Engine\StreamBuilder;
use Engine\Text;
use Engine\TextSize;
use Engine\Widget;

final class OrderConfirmationPage extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    public function build(): Widget
    {
        require_once dirname(__DIR__, 2) . '/backend/vendor/autoload.php';

        $orderId = (int) $this->params['id'];
        $order = (new OrderRepository())->find($orderId);

        if ($order === null) {
            return Scaffold::make(
                body: Text::make('Commande introuvable.'),
                appBar: AppBar::make('Commande', backHref: '/'),
            );
        }

        $total = number_format($order['total_cents'] / 100, 2, ',', ' ') . ' €';

        return Scaffold::make(
            body: Column::make([
                Text::make('Merci, ' . $order['customer_name'] . ' !', size: TextSize::XL2, weight: FontWeight::BOLD, color: Color::green(600)),
                Text::make("Commande #{$orderId} — {$total}"),
                Text::make('Livraison : ' . $order['address'], color: Color::gray(600)),
                Text::make('Suivi en direct', size: TextSize::LG, weight: FontWeight::SEMIBOLD),
                StreamBuilder::make(
                    "/fragment/order-status/{$orderId}",
                    Text::make('Statut : ' . (new OrderRepository())->status($orderId)),
                    intervalMs: 3000,
                ),
                Link::make("Retour à l'accueil", '/'),
            ], 'flex flex-col gap-3 p-4'),
            appBar: AppBar::make('Confirmation'),
        );
    }
}
