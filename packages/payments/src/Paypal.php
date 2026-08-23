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
 * PayPal checkout — same REST, server-to-server idiom as
 * Engine\Payments\Feexpay/Fedapay/Stripe (file_get_contents()/
 * stream_context_create(), no curl and no PayPal SDK — the PHP binary
 * cross-compiled for Android has no curl extension, see Feexpay's own
 * docblock).
 *
 * Orders API v2 (docs.developer.paypal.com/docs/api/orders/v2/), hosted
 * approval flow: pay() creates an Order (intent CAPTURE) and returns the
 * `links` entry with rel "approve" — Engine\Device\UrlLauncher opens it
 * from a native screen, same shape as Fedapay::pay()/Stripe::pay(). The
 * customer approves on PayPal's own hosted page, NOT inside this app.
 *
 * Unlike Stripe's Checkout Sessions, PayPal orders need an EXPLICIT
 * capture call after approval — approval alone doesn't move money.
 * captureAction()'s result is the actual "did this succeed" signal;
 * status() alone (a plain GET) only ever reports the order's lifecycle
 * state (CREATED/APPROVED/COMPLETED...), it does not capture funds.
 *
 * Auth is OAuth2 client-credentials, not a static secret-key Bearer
 * header like the other gateways here — getAccessToken() exchanges
 * $clientId/$clientSecret for a short-lived token before every call
 * (no caching across requests: PHP process-per-request on Android/CLI
 * dev server means there's no long-lived process to cache it in).
 */
final class Paypal
{
    private const SANDBOX_BASE_URL = 'https://api-m.sandbox.paypal.com';
    private const LIVE_BASE_URL = 'https://api-m.paypal.com';

    /**
     * Creates an Order and returns the hosted approval URL to redirect
     * the customer to.
     *
     * @return array{order_id: string, url: string}|false
     */
    public static function pay(
        string $clientId,
        string $clientSecret,
        float $amount,
        string $currency,
        string $returnUrl,
        string $cancelUrl,
        bool $sandbox = true,
    ): array|false {
        $baseUrl = $sandbox ? self::SANDBOX_BASE_URL : self::LIVE_BASE_URL;

        $accessToken = self::getAccessToken($baseUrl, $clientId, $clientSecret);
        if ($accessToken === null) {
            return false;
        }

        $order = self::request($baseUrl . '/v2/checkout/orders', $accessToken, 'POST', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => $currency,
                    'value' => number_format($amount, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ]);

        $orderId = $order['id'] ?? null;
        $links = $order['links'] ?? [];
        $approveUrl = null;
        foreach (is_array($links) ? $links : [] as $link) {
            if (($link['rel'] ?? null) === 'approve') {
                $approveUrl = $link['href'] ?? null;
                break;
            }
        }

        if (!is_string($orderId) || !is_string($approveUrl) || $approveUrl === '') {
            return false;
        }

        return ['order_id' => $orderId, 'url' => $approveUrl];
    }

    /**
     * Captures an approved order — the step that actually moves money.
     * Only meaningful after the customer has completed PayPal's own
     * approval page (see pay()'s docblock); capturing an order still
     * pending approval fails.
     *
     * @return array{status: string|null}|false
     */
    public static function capture(string $clientId, string $clientSecret, string $orderId, bool $sandbox = true): array|false
    {
        $baseUrl = $sandbox ? self::SANDBOX_BASE_URL : self::LIVE_BASE_URL;

        $accessToken = self::getAccessToken($baseUrl, $clientId, $clientSecret);
        if ($accessToken === null) {
            return false;
        }

        $result = self::request($baseUrl . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', $accessToken, 'POST', []);

        if (!isset($result['status'])) {
            return false;
        }

        return ['status' => $result['status']];
    }

    /**
     * Real server-to-server status check — a plain GET, does NOT capture
     * funds (see capture()).
     *
     * @return array{status: string|null}|false
     */
    public static function status(string $clientId, string $clientSecret, string $orderId, bool $sandbox = true): array|false
    {
        $baseUrl = $sandbox ? self::SANDBOX_BASE_URL : self::LIVE_BASE_URL;

        $accessToken = self::getAccessToken($baseUrl, $clientId, $clientSecret);
        if ($accessToken === null) {
            return false;
        }

        $result = self::request($baseUrl . '/v2/checkout/orders/' . rawurlencode($orderId), $accessToken, 'GET', null);

        if (!isset($result['status'])) {
            return false;
        }

        return ['status' => $result['status']];
    }

    private static function getAccessToken(string $baseUrl, string $clientId, string $clientSecret): ?string
    {
        $response = @file_get_contents($baseUrl . '/v1/oauth2/token', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded' . "\r\n"
                    . 'Authorization: Basic ' . base64_encode("{$clientId}:{$clientSecret}") . "\r\n",
                'content' => 'grant_type=client_credentials',
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]));

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        $token = is_array($data) ? ($data['access_token'] ?? null) : null;

        return is_string($token) ? $token : null;
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private static function request(string $url, string $accessToken, string $method, ?array $body): array
    {
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => $method,
                'header' => "Content-Type: application/json\r\n"
                    . "Accept: application/json\r\n"
                    . "Authorization: Bearer {$accessToken}\r\n",
                'content' => $body !== null ? json_encode($body, JSON_THROW_ON_ERROR) : '',
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
