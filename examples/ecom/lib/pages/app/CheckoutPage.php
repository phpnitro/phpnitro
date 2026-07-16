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
use Engine\KkiapayButton;
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
     * Demo path when no Kkiapay key is configured (see build()) — validates
     * and creates the order directly, no payment step.
     *
     * @param array<string, string> $data
     */
    protected function onConfirm(array $data): ?string
    {
        return $this->validateAndCreateOrder($data);
    }

    /**
     * KkiapayButton's success callback posts here with the shipping form's
     * fields (name, address, terms...) AND transaction_id together — see
     * KkiapayButton's docblock on why the enclosing <form> gets serialized.
     *
     * @param array<string, string> $data
     */
    protected function onConfirmPayment(array $data): ?string
    {
        $transactionId = trim($data['transaction_id'] ?? '');

        if ($transactionId === '' || !$this->verifyKkiapayTransaction($transactionId)) {
            $this->state['error'] = "Le paiement n'a pas pu être vérifié.";

            return null;
        }

        return $this->validateAndCreateOrder($data);
    }

    /**
     * @param array<string, string> $data
     */
    private function validateAndCreateOrder(array $data): ?string
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

        [$items, $totalCents] = $this->cartToOrderLines($cartItems);

        $orderId = (new OrderRepository())->create($data['name'], $data['address'], $items, $totalCents);
        Cart::clear();

        return "/order/{$orderId}";
    }

    /**
     * @param array<int, int> $cartItems product id => quantity
     * @return array{0: array<int, array{name: string, quantity: int, price_cents: int}>, 1: int}
     */
    private function cartToOrderLines(array $cartItems): array
    {
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

        return [$items, $totalCents];
    }

    /**
     * Client-side success is only a UI signal (see KkiapayButton's
     * docblock) — a real deployment must call Kkiapay's server-to-server
     * verify API with the PRIVATE key before trusting it. That call isn't
     * exercised by anything in this codebase (no sandbox account available
     * here to test against), so double-check it against Kkiapay's current
     * API docs before relying on it in production.
     *
     * Without a configured private key, this is a demo/sandbox app with no
     * real money moving through it, so the client-side signal is trusted
     * as-is — never do this for a real store.
     */
    private function verifyKkiapayTransaction(string $transactionId): bool
    {
        $privateKey = $_ENV['KKIAPAY_PRIVATE_KEY'] ?? '';

        if ($privateKey === '') {
            return true;
        }

        try {
            $response = file_get_contents(
                "https://api.kkiapay.me/api/v1/transactions/status?transactionId={$transactionId}",
                false,
                stream_context_create([
                    'http' => [
                        'header' => "x-api-key: {$privateKey}\r\n",
                        'timeout' => 10,
                    ],
                ]),
            );

            if ($response === false) {
                return false;
            }

            $data = json_decode($response, true);

            return ($data['status'] ?? null) === 'SUCCESS';
        } catch (\Throwable) {
            return false;
        }
    }

    public function build(): Widget
    {
        $children = [];

        if ($this->state['error'] !== null) {
            $children[] = Text::make($this->state['error'], color: Color::red(600));
        }

        $publicKey = $_ENV['KKIAPAY_PUBLIC_KEY'] ?? '';
        [, $totalCents] = $this->cartToOrderLines(Cart::items());

        // Amount/currency must match what your Kkiapay account is
        // configured for — this demo just reuses the shop's own euro
        // totals (see CartPage/ProductPage), which won't be right for a
        // real XOF-denominated Kkiapay account.
        $payButton = $publicKey !== ''
            ? KkiapayButton::make(
                $publicKey,
                $totalCents / 100,
                action: 'confirmPayment',
                label: 'Payer et valider la commande',
            )
            : Button::make(
                'Valider la commande (mode démo, sans paiement)',
                classes: 'bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg w-full',
            );

        $children[] = Form::make([
            TextField::make('name', label: 'Nom complet'),
            TextField::make('address', label: 'Adresse de livraison'),
            SelectBox::make('delivery', [
                'standard' => 'Standard (3-5 jours)',
                'express' => 'Express (24h)',
            ], selected: 'standard', label: 'Mode de livraison'),
            Checkbox::make('terms', "J'accepte les conditions générales"),
            FingerprintButton::make('Confirmer avec biométrie'),
            $payButton,
        ], action: 'confirm');

        $children[] = Link::make('Retour au panier', '/cart');

        return Scaffold::make(
            body: Column::make($children, 'flex flex-col gap-4 p-4'),
            appBar: AppBar::make('Paiement', backHref: '/cart'),
        );
    }
}
