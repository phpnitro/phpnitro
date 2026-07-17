<?php

namespace Engine;

/**
 * Minimal wrapper around Stripe's REST API to create a Checkout Session and
 * get its hosted-page URL — no stripe-php SDK dependency, just the
 * documented HTTP endpoint (POST /v1/checkout/sessions, Bearer auth with
 * the secret key, form-encoded body). Not tested against a real Stripe
 * account in this environment (no sandbox credentials available) — verify
 * against Stripe's current API docs before relying on this in production.
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

        try {
            $response = file_get_contents('https://api.stripe.com/v1/checkout/sessions', false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Authorization: Bearer {$secretKey}\r\nContent-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $params,
                    'timeout' => 10,
                    'ignore_errors' => true,
                ],
            ]));

            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);

            return $data['url'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
