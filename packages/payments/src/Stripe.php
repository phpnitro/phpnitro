<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Payments;

/**
 * Stripe checkout — same REST, server-to-server idiom as
 * Engine\Payments\Feexpay/Fedapay (file_get_contents()/
 * stream_context_create(), no curl and no stripe-php vendor SDK — the
 * PHP binary cross-compiled for Android has no curl extension, see
 * Feexpay's own docblock).
 *
 * The previous Stripe integration (removed, see git history) split into
 * Stripe::cardElement() (a Stripe Elements iframe, mounted into a DOM
 * element) and a separate StripeCheckout helper for PaymentIntents —
 * both need a live DOM to mount into, impossible against this app's
 * native Canvas rendering. Only the OLD StripeCheckout::createSessionUrl()
 * half survives here, rebuilt as pay(): Stripe's hosted Checkout Session
 * page needs no DOM at all — Engine\Device\UrlLauncher opens it from a
 * native screen, same "redirect to a hosted page" shape as
 * Fedapay::pay()/Feexpay::payByWebUrl(). Same "client-side return is a
 * UI signal only" rule as every other gateway here: status() (a real
 * server-to-server GET, using the SECRET key) is what actually confirms
 * a payment.
 *
 * Endpoints/field names below are Stripe's own stable, long-documented
 * REST API (Checkout Sessions) — unlike Fedapay.php's docblock, there is
 * no live-verified discrepancy to report here: **this class has not
 * been tested against a real Stripe account** (no sandbox/test-mode
 * credentials available yet) — verify against a real test-mode key
 * before relying on this in production.
 */
final class Stripe
{
    private const BASE_URL = 'https://api.stripe.com/v1';

    /**
     * Stripe's request bodies are form-encoded, not JSON — http_build_query()
     * on a nested array already produces the bracket notation
     * (`line_items[0][price_data][currency]=...`) Stripe's API expects,
     * no manual string-building needed.
     *
     * $amount is the currency's MAJOR unit (9.99 for "$9.99"), not cents
     * — self::minorUnits() converts using Stripe's own zero-decimal
     * currency list (XOF/XAF among them — no subunit at all, unlike most
     * currencies Stripe supports).
     *
     * @return array{url: string, session_id: string}|false
     */
    public static function pay(
        string $secretKey,
        float $amount,
        string $currency,
        string $productName,
        string $successUrl,
        string $cancelUrl,
        string $reference,
        ?string $customerEmail = null,
    ): array|false {
        $params = http_build_query(array_filter([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $reference,
            'customer_email' => $customerEmail,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => self::minorUnits($amount, $currency),
                    'product_data' => ['name' => $productName],
                ],
            ]],
        ], static fn (mixed $value): bool => $value !== null));

        $session = self::request($secretKey, 'POST', self::BASE_URL . '/checkout/sessions', $params);

        $url = $session['url'] ?? null;
        $sessionId = $session['id'] ?? null;
        if (!is_string($url) || $url === '' || !is_string($sessionId)) {
            return false;
        }

        return ['url' => $url, 'session_id' => $sessionId];
    }

    /**
     * Stripe's own documented zero-decimal currency list (BIF, CLP, DJF,
     * GNF, JPY, KMF, KRW, MGA, PYG, RWF, UGX, VND, VUV, XAF, XOF, XPF) —
     * unit_amount is the amount AS-IS for these, amount * 100 (cents) for
     * every other currency Stripe supports.
     */
    private static function minorUnits(float $amount, string $currency): int
    {
        $zeroDecimal = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

        return in_array(strtolower($currency), $zeroDecimal, true)
            ? (int) round($amount)
            : (int) round($amount * 100);
    }

    /**
     * Real server-to-server status check — $sessionId is what pay()
     * returned. `payment_status` ('paid'/'unpaid'/'no_payment_required')
     * is what actually matters; `status` ('open'/'complete'/'expired')
     * is the Checkout Session's own lifecycle, included for completeness.
     *
     * @return array{payment_status: string|null, status: string|null, reference: string}|false
     */
    public static function status(string $secretKey, string $sessionId): array|false
    {
        $encodedId = rawurlencode($sessionId);
        $data = self::request($secretKey, 'GET', self::BASE_URL . "/checkout/sessions/{$encodedId}");

        if (!isset($data['id'])) {
            return false;
        }

        return [
            'payment_status' => $data['payment_status'] ?? null,
            'status' => $data['status'] ?? null,
            'reference' => $data['client_reference_id'] ?? $sessionId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function request(string $secretKey, string $method, string $url, ?string $body = null): array
    {
        $response = @file_get_contents($url, false, stream_context_create([
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

        $data = json_decode($response, true);

        return is_array($data) ? $data : [];
    }
}
