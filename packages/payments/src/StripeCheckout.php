<?php

namespace Engine\Payments;

/**
 * Minimal wrapper around Stripe's REST API — hosted Checkout Sessions
 * (createSessionUrl) and PaymentIntents (createPaymentIntent/
 * retrievePaymentIntent, used by StripeCardField's own Elements-based card
 * field) — no stripe-php SDK dependency, just the documented HTTP
 * endpoints (Bearer auth with the secret key, form-encoded bodies). Not
 * tested against a real Stripe account in this environment (no sandbox
 * credentials available) — verify against Stripe's current API docs
 * before relying on this in production.
 */
final class StripeCheckout
{
    public static function createSessionUrl(
        string $secretKey,
        int $amountCents,
        string $currency,
        string $productName,
        string $successUrl,
        string $cancelUrl,
    ): ?string {
        $params = http_build_query([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amountCents,
                    'product_data' => ['name' => $productName],
                ],
            ]],
        ]);

        $data = self::request($secretKey, 'POST', 'https://api.stripe.com/v1/checkout/sessions', $params);

        return $data['url'] ?? null;
    }

    /**
     * Creates a PaymentIntent server-side (POST /v1/payment_intents) —
     * the client_secret it returns is what StripeCardField needs to mount
     * Stripe Elements and confirm the card client-side. Same REST-only
     * approach as createSessionUrl(), same "not tested against a real
     * Stripe account" caveat.
     *
     * @return array{id: string, client_secret: string}|null
     */
    public static function createPaymentIntent(string $secretKey, int $amountCents, string $currency): ?array
    {
        $params = http_build_query([
            'amount' => $amountCents,
            'currency' => $currency,
            'automatic_payment_methods[enabled]' => 'true',
        ]);

        $data = self::request($secretKey, 'POST', 'https://api.stripe.com/v1/payment_intents', $params);

        if (!isset($data['id'], $data['client_secret'])) {
            return null;
        }

        return ['id' => $data['id'], 'client_secret' => $data['client_secret']];
    }

    /**
     * Re-fetches a PaymentIntent server-side (GET /v1/payment_intents/{id})
     * to confirm its real status before creating an order — the client's
     * "it succeeded" signal is never trusted alone, same discipline as
     * every other gateway here.
     *
     * @return array{id: string, status: string}|null
     */
    public static function retrievePaymentIntent(string $secretKey, string $paymentIntentId): ?array
    {
        $encodedId = rawurlencode($paymentIntentId);
        $data = self::request($secretKey, 'GET', "https://api.stripe.com/v1/payment_intents/{$encodedId}");

        if (!isset($data['id'], $data['status'])) {
            return null;
        }

        return ['id' => $data['id'], 'status' => $data['status']];
    }

    /**
     * @return array<string, mixed>
     */
    private static function request(string $secretKey, string $method, string $url, ?string $body = null): array
    {
        try {
            $response = file_get_contents($url, false, stream_context_create([
                'http' => [
                    'method' => $method,
                    'header' => "Authorization: Bearer {$secretKey}\r\nContent-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $body,
                    'timeout' => 10,
                    'ignore_errors' => true,
                ],
            ]));

            if ($response === false) {
                return [];
            }

            return json_decode($response, true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }
}
