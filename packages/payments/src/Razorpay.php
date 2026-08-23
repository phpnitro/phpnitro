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
 * Razorpay checkout — same REST, server-to-server idiom as
 * Engine\Payments\Feexpay/Fedapay/Stripe/Paypal (file_get_contents()/
 * stream_context_create(), no curl and no razorpay-php SDK — the PHP
 * binary cross-compiled for Android has no curl extension, see
 * Feexpay's own docblock).
 *
 * Razorpay's Standard Checkout is a JS widget (Checkout.js, mounted
 * into a DOM element) — impossible against this app's native Canvas
 * rendering, same reasoning as Fedapay/Stripe's own docblocks. Payment
 * Links (docs razorpay.com/docs/api/payments/payment-links/) is the
 * hosted-page equivalent instead: pay() creates one and returns its
 * `short_url` — Engine\Device\UrlLauncher opens it from a native
 * screen, same shape as every other gateway here.
 *
 * Auth is HTTP Basic with $keyId:$keySecret (like Stripe's Bearer
 * secret key, just Basic instead) — same key pair for every call, no
 * separate token exchange (unlike Paypal).
 */
final class Razorpay
{
    private const BASE_URL = 'https://api.razorpay.com/v1';

    /**
     * Creates a Payment Link and returns its hosted URL.
     *
     * $amount is the currency's MAJOR unit (9.99 for "9.99 INR"), not
     * paise — self::minorUnits() converts (Razorpay always wants the
     * smallest unit, no zero-decimal-currency exception like Stripe's).
     *
     * @return array{id: string, url: string}|false
     */
    public static function pay(
        string $keyId,
        string $keySecret,
        float $amount,
        string $currency,
        string $description,
        string $reference,
        string $callbackUrl,
    ): array|false {
        $link = self::request('POST', '/payment_links/', $keyId, $keySecret, [
            'amount' => self::minorUnits($amount),
            'currency' => $currency,
            'description' => $description,
            'reference_id' => $reference,
            'callback_url' => $callbackUrl,
            'callback_method' => 'get',
        ]);

        $id = $link['id'] ?? null;
        $url = $link['short_url'] ?? null;
        if (!is_string($id) || !is_string($url) || $url === '') {
            return false;
        }

        return ['id' => $id, 'url' => $url];
    }

    private static function minorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Real server-to-server status check. `status` is 'created',
     * 'partially_paid', 'paid', 'expired', or 'cancelled' — only 'paid'
     * means the customer actually completed payment.
     *
     * @return array{status: string|null, reference: string|null}|false
     */
    public static function status(string $keyId, string $keySecret, string $paymentLinkId): array|false
    {
        $link = self::request('GET', '/payment_links/' . rawurlencode($paymentLinkId), $keyId, $keySecret, null);

        if (!isset($link['id'])) {
            return false;
        }

        return [
            'status' => $link['status'] ?? null,
            'reference' => $link['reference_id'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private static function request(string $method, string $path, string $keyId, string $keySecret, ?array $body): array
    {
        $response = @file_get_contents(self::BASE_URL . $path, false, stream_context_create([
            'http' => [
                'method' => $method,
                'header' => "Content-Type: application/json\r\n"
                    . "Accept: application/json\r\n"
                    . 'Authorization: Basic ' . base64_encode("{$keyId}:{$keySecret}") . "\r\n",
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
