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
 * PayDunya checkout — same REST, server-to-server idiom as
 * Engine\Payments\Feexpay/Fedapay/Stripe/Paypal/Razorpay
 * (file_get_contents()/stream_context_create(), no curl and no
 * paydunya/paydunya-php vendor SDK — the PHP binary cross-compiled for
 * Android has no curl extension, see Feexpay's own docblock).
 *
 * Endpoints/fields below are read straight from PayDunya's own official
 * SDK source (github.com/paydunyadev/paydunya-php,
 * paydunya/checkout/checkout_invoice.php + paydunya/setup.php +
 * paydunya/utilities.php) rather than the public docs site, which
 * blocks non-browser requests outright (a real HTTP 403 on every page
 * tried) — same "vendor source is the real source of truth for the
 * wire format" reasoning as Feexpay's own rewrite.
 *
 * pay() creates a Checkout Invoice and returns its hosted URL —
 * Engine\Device\UrlLauncher opens it from a native screen, same shape
 * as every other gateway here. Auth is 4 static headers (master/
 * private/public key + a separate account-level token), sent on every
 * call — no OAuth exchange, no per-request Bearer secret like Stripe.
 */
final class Paydunya
{
    private const SANDBOX_BASE_URL = 'https://app.paydunya.com/sandbox-api/v1';
    private const LIVE_BASE_URL = 'https://app.paydunya.com/api/v1';

    /**
     * @return array{token: string, url: string}|false
     */
    public static function pay(
        string $masterKey,
        string $privateKey,
        string $publicKey,
        string $token,
        float $amount,
        string $description,
        string $storeName,
        string $returnUrl,
        string $cancelUrl,
        string $callbackUrl,
        bool $sandbox = true,
    ): array|false {
        $baseUrl = $sandbox ? self::SANDBOX_BASE_URL : self::LIVE_BASE_URL;

        $invoice = self::request($baseUrl . '/checkout-invoice/create', $masterKey, $privateKey, $publicKey, $token, $sandbox, [
            'invoice' => [
                'items' => [],
                'taxes' => [],
                'total_amount' => round($amount, 2),
                'description' => $description,
                'channels' => [],
            ],
            'store' => [
                'name' => $storeName,
            ],
            'custom_data' => [],
            'actions' => [
                'cancel_url' => $cancelUrl,
                'return_url' => $returnUrl,
                'callback_url' => $callbackUrl,
            ],
        ]);

        if (($invoice['response_code'] ?? null) !== '00') {
            return false;
        }

        $invoiceToken = $invoice['token'] ?? null;
        $url = $invoice['response_text'] ?? null;
        if (!is_string($invoiceToken) || !is_string($url) || $url === '') {
            return false;
        }

        return ['token' => $invoiceToken, 'url' => $url];
    }

    /**
     * Real server-to-server status check. `status` is 'completed' on a
     * genuinely finished payment — anything else (e.g. 'pending',
     * 'cancelled') means don't trust it yet, same rule as every other
     * gateway here.
     *
     * @return array{status: string|null, amount: mixed}|false
     */
    public static function status(
        string $masterKey,
        string $privateKey,
        string $publicKey,
        string $token,
        string $invoiceToken,
        bool $sandbox = true,
    ): array|false {
        $baseUrl = $sandbox ? self::SANDBOX_BASE_URL : self::LIVE_BASE_URL;

        $result = self::request(
            $baseUrl . '/checkout-invoice/confirm/' . rawurlencode($invoiceToken),
            $masterKey,
            $privateKey,
            $publicKey,
            $token,
            $sandbox,
            null,
        );

        if (!isset($result['status'])) {
            return false;
        }

        return [
            'status' => $result['status'],
            'amount' => $result['invoice']['total_amount'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private static function request(
        string $url,
        string $masterKey,
        string $privateKey,
        string $publicKey,
        string $token,
        bool $sandbox,
        ?array $body,
    ): array {
        $headers = "Accept: application/json\r\n"
            . "PAYDUNYA-MASTER-KEY: {$masterKey}\r\n"
            . "PAYDUNYA-PRIVATE-KEY: {$privateKey}\r\n"
            . "PAYDUNYA-PUBLIC-KEY: {$publicKey}\r\n"
            . "PAYDUNYA-TOKEN: {$token}\r\n"
            . 'PAYDUNYA-MODE: ' . ($sandbox ? 'test' : 'live') . "\r\n";

        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => $body !== null ? 'POST' : 'GET',
                'header' => $body !== null ? "Content-Type: application/json\r\n{$headers}" : $headers,
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
