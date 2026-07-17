<?php

namespace Engine\App;

use Backend\Repository\OrderRepository;
use Backend\Repository\ProductRepository;
use Engine\AppBar;
use Engine\Button;
use Engine\Checkbox;
use Engine\Column;
use Engine\ErrorBanner;
use Engine\FingerprintButton;
use Engine\Form;
use Engine\Link;
use Engine\Payments\FedapayButton;
use Engine\Payments\FeexpayButton;
use Engine\Payments\IziChangePayButton;
use Engine\Payments\KkiapayButton;
use Engine\Payments\PaypalButton;
use Engine\Payments\StripeButton;
use Engine\Payments\StripeCardField;
use Engine\Payments\StripeCheckout;
use Engine\Payments\TresorPayButton;
use Engine\Scaffold;
use Engine\Screen;
use Engine\SelectBox;
use Engine\TextField;
use Engine\Widget;

/**
 * Picks ONE payment method to show on /checkout, whichever comes first in
 * this priority list with its key(s) configured in .env — see
 * ../../phpnitro.yml for the full list of env var names. No key configured
 * anywhere -> demo mode (order created directly, no payment).
 */
final class CheckoutPage extends Screen
{
    protected function initialState(): array
    {
        return ['error' => null];
    }

    /**
     * Demo path when no gateway is configured (see build()) — validates
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
    protected function onConfirmKkiapay(array $data): ?string
    {
        return $this->verifyThenCreateOrder($data, 'transaction_id', $this->verifyKkiapayTransaction(...));
    }

    /**
     * @param array<string, string> $data
     */
    protected function onConfirmPaypal(array $data): ?string
    {
        return $this->verifyThenCreateOrder($data, 'paypal_order_id', $this->verifyPaypalOrder(...));
    }

    /**
     * @param array<string, string> $data
     */
    protected function onConfirmFedapay(array $data): ?string
    {
        return $this->verifyThenCreateOrder($data, 'transaction_id', $this->verifyFedapayTransaction(...));
    }

    /**
     * StripeCardField already confirmed the card client-side
     * (stripe.confirmCardPayment) before posting here — this re-fetches
     * the PaymentIntent server-side to confirm its real status is
     * "succeeded" rather than trusting that client signal alone, same
     * discipline as every other gateway above.
     *
     * @param array<string, string> $data
     */
    protected function onConfirmStripeCard(array $data): ?string
    {
        return $this->verifyThenCreateOrder($data, 'payment_intent_id', $this->verifyStripePaymentIntent(...));
    }

    /**
     * @param array<string, string> $data
     */
    protected function onConfirmFeexpay(array $data): ?string
    {
        return $this->verifyThenCreateOrder($data, 'transaction_id', fn (string $id) => $this->trustOnlyInDemoMode('FEEXPAY_API_KEY'));
    }

    /**
     * @param array<string, string> $data
     */
    protected function onConfirmIzichangepay(array $data): ?string
    {
        return $this->verifyThenCreateOrder($data, 'transaction_id', fn (string $id) => $this->trustOnlyInDemoMode('IZICHANGEPAY_API_SECRET'));
    }

    /**
     * @param array<string, string> $data
     */
    protected function onConfirmTresorpay(array $data): ?string
    {
        return $this->verifyThenCreateOrder($data, 'transaction_id', fn (string $id) => $this->trustOnlyInDemoMode('TRESORPAY_SECRET_KEY'));
    }

    /**
     * Stripe Checkout has no client-side callback at all (StripeButton is a
     * plain submit button) — this creates the order right away, then asks
     * Stripe for a hosted Checkout Session and redirects there.
     *
     * This is optimistic: the order exists before Stripe actually confirms
     * payment, because this demo has no webhook endpoint to react
     * asynchronously. A real store must mark orders paid from Stripe's
     * webhook (signature verified) instead — same "don't trust the
     * redirect alone" rule as every other gateway here, just with a bigger
     * gap since there's no callback yet in this codebase at all.
     *
     * @param array<string, string> $data
     */
    protected function onConfirmStripe(array $data): ?string
    {
        [, $totalCents] = $this->cartToOrderLines(Cart::items());

        $redirect = $this->validateAndCreateOrder($data);
        if ($redirect === null) {
            return null;
        }

        $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';
        $baseUrl = (($_SERVER['HTTPS'] ?? '') !== '' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        $sessionUrl = StripeCheckout::createSessionUrl(
            $secretKey,
            $totalCents,
            'eur',
            'Commande Ma Boutique',
            $baseUrl . $redirect,
            $baseUrl . '/checkout',
        );

        return $sessionUrl ?? $redirect;
    }

    /**
     * @param array<string, string> $data
     */
    private function verifyThenCreateOrder(array $data, string $transactionField, callable $verify): ?string
    {
        $transactionId = trim($data[$transactionField] ?? '');

        if ($transactionId === '' || !$verify($transactionId)) {
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
                        'ignore_errors' => true,
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

    /**
     * Real PayPal OAuth2 client-credentials + capture flow (client secret
     * exchanged for a bearer token, then POST .../capture) — matches
     * PayPal's documented API, but untested against a real sandbox app in
     * this environment. Uses the SANDBOX host; switch to api-m.paypal.com
     * for a live account.
     */
    private function verifyPaypalOrder(string $orderId): bool
    {
        $clientId = $_ENV['PAYPAL_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? '';

        if ($clientSecret === '') {
            return true;
        }

        try {
            $base = 'https://api-m.sandbox.paypal.com';
            $auth = base64_encode("{$clientId}:{$clientSecret}");

            $tokenResponse = file_get_contents("{$base}/v1/oauth2/token", false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Authorization: Basic {$auth}\r\nContent-Type: application/x-www-form-urlencoded\r\n",
                    'content' => 'grant_type=client_credentials',
                    'timeout' => 10,
                    'ignore_errors' => true,
                ],
            ]));

            $accessToken = $tokenResponse !== false ? (json_decode($tokenResponse, true)['access_token'] ?? null) : null;
            if ($accessToken === null) {
                return false;
            }

            $captureResponse = file_get_contents("{$base}/v2/checkout/orders/{$orderId}/capture", false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\n",
                    'content' => '',
                    'timeout' => 10,
                    'ignore_errors' => true,
                ],
            ]));

            if ($captureResponse === false) {
                return false;
            }

            return (json_decode($captureResponse, true)['status'] ?? null) === 'COMPLETED';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * PaymentIntents.retrieve — same official Stripe REST endpoint as
     * createPaymentIntent, untested against a real Stripe account here.
     */
    private function verifyStripePaymentIntent(string $paymentIntentId): bool
    {
        $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';

        if ($secretKey === '') {
            return false;
        }

        $intent = StripeCheckout::retrievePaymentIntent($secretKey, $paymentIntentId);

        return ($intent['status'] ?? null) === 'succeeded';
    }

    /**
     * FedaPay's transaction-status endpoint, Bearer auth with the secret
     * key — moderate confidence in this shape (not Kkiapay/PayPal level),
     * untested against a real sandbox account.
     */
    private function verifyFedapayTransaction(string $transactionId): bool
    {
        $secretKey = $_ENV['FEDAPAY_SECRET_KEY'] ?? '';

        if ($secretKey === '') {
            return true;
        }

        try {
            $response = file_get_contents("https://api.fedapay.com/v1/transactions/{$transactionId}", false, stream_context_create([
                'http' => [
                    'header' => "Authorization: Bearer {$secretKey}\r\n",
                    'timeout' => 10,
                    'ignore_errors' => true,
                ],
            ]));

            if ($response === false) {
                return false;
            }

            $data = json_decode($response, true);
            $status = $data['v1/transaction']['status'] ?? $data['transaction']['status'] ?? null;

            return $status === 'approved';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Feexpay/iZiChangePay/TresorPay have no confirmed server-to-server
     * verify endpoint implemented here (see each widget's docblock — low
     * to very-low confidence in their exact API). Rather than fake a call
     * to an unverified endpoint, this refuses once a secret key is
     * configured (signalling "this is meant to be a real deployment")
     * instead of silently trusting the client. Demo mode (no secret key)
     * still works, same as every other gateway.
     */
    private function trustOnlyInDemoMode(string $secretEnvKey): bool
    {
        return ($_ENV[$secretEnvKey] ?? '') === '';
    }

    public function build(): Widget
    {
        $children = [ErrorBanner::make($this->state['error'])];

        [, $totalCents] = $this->cartToOrderLines(Cart::items());
        $amount = $totalCents / 100;

        $payButton = $this->selectPaymentWidget($amount);

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

    /**
     * Amount/currency reuses the shop's own euro totals (see
     * CartPage/ProductPage) for every gateway here — won't be right for an
     * account configured in XOF/USD/etc, adjust per gateway as needed.
     */
    private function selectPaymentWidget(float $amount): Widget
    {
        if (($key = $_ENV['KKIAPAY_PUBLIC_KEY'] ?? '') !== '') {
            return KkiapayButton::make($key, $amount, action: 'confirmKkiapay', label: 'Payer avec Kkiapay');
        }

        if (($key = $_ENV['PAYPAL_CLIENT_ID'] ?? '') !== '') {
            return PaypalButton::make($key, $amount, action: 'confirmPaypal');
        }

        if (($key = $_ENV['FEDAPAY_PUBLIC_KEY'] ?? '') !== '') {
            return FedapayButton::make($key, $amount, action: 'confirmFedapay', description: 'Commande Ma Boutique', label: 'Payer avec FedaPay');
        }

        if (($publicKey = $_ENV['STRIPE_PUBLIC_KEY'] ?? '') !== '' && ($secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? '') !== '') {
            $intent = StripeCheckout::createPaymentIntent($secretKey, (int) round($amount * 100), 'eur');

            if ($intent !== null) {
                return StripeCardField::make($publicKey, $intent['client_secret'], action: 'confirmStripeCard');
            }
            // PaymentIntent creation failed (bad key, network...) — fall
            // through to the hosted-checkout path below instead of
            // rendering a card field with no PaymentIntent to confirm.
        }

        if (($_ENV['STRIPE_SECRET_KEY'] ?? '') !== '') {
            return StripeButton::make(action: 'confirmStripe', label: 'Payer par carte (Stripe)');
        }

        if (($key = $_ENV['FEEXPAY_SHOP_ID'] ?? '') !== '') {
            return FeexpayButton::make($key, $amount, action: 'confirmFeexpay', label: 'Payer avec Feexpay');
        }

        if (($key = $_ENV['IZICHANGEPAY_API_KEY'] ?? '') !== '') {
            return IziChangePayButton::make($key, $amount, action: 'confirmIzichangepay', label: 'Payer avec iZiChangePay');
        }

        if (($key = $_ENV['TRESORPAY_PUBLIC_KEY'] ?? '') !== '') {
            return TresorPayButton::make($key, $amount, action: 'confirmTresorpay', label: 'Payer avec TresorPay');
        }

        return Button::make(
            'Valider la commande (mode démo, sans paiement)',
            classes: 'bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg w-full',
        );
    }
}
