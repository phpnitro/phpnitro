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
 * Feexpay checkout — talks to Feexpay's REST API directly via
 * file_get_contents()/stream_context_create(), the same idiom every other
 * server-to-server call in this codebase uses (StripeCheckout,
 * Engine\SocialAuth\OAuthProvider), instead of depending on the
 * `feexpay/feexpay-php` vendor SDK this class used previously.
 *
 * That switch isn't a style preference: the vendor SDK calls raw
 * `curl_*` functions, and the PHP binary cross-compiled for Android
 * (`android/README.md`'s php-ndk build) has no `curl` extension —
 * confirmed on a real device (Infinix X6532), where every call failed
 * instantly with "Undefined constant ... CURLOPT_POST" (curl's constants
 * don't exist without the extension), silently swallowed by the vendor
 * SDK's own try/catch into a bare `false`. The `https://` stream wrapper
 * this class relies on instead needs the `openssl` extension, which the
 * same php-ndk build didn't have either — both gaps closed together (see
 * the Dockerfile change alongside this file). A `curl`-based fix alone
 * would only have fixed Feexpay; the streams approach also matches what
 * already works for Stripe/OAuth on this platform.
 *
 * Endpoints and field names below are copied from the installed
 * `vendor/feexpay/feexpay-php` v2.0 source (`FeexpayClass.php`), not from
 * Feexpay's own published docs (which describe an older, different
 * signature) — that vendor source remains the source of truth for the
 * wire format even though this class no longer depends on the package
 * itself.
 *
 * Two vendor-API quirks carried over as-is:
 * - `$sandbox` is accepted for API stability (call sites already pass it)
 *   but changes nothing: the vendor SDK's own `$mode` ('SANDBOX'/'LIVE')
 *   is only ever read by its client-side JS widget helper, never by the
 *   REST calls this class makes — there is only one real API base URL.
 * - `status()` on an unknown/not-yet-settled reference returns an array
 *   with fields present in the raw JSON but likely `null` — callers must
 *   treat a `null` status as "not settled yet", not as an error.
 */
final class Feexpay
{
    private const BASE_URL = 'https://api-v2.feexpay.me/api';

    /**
     * Triggers a real USSD push on the customer's phone (mobile money —
     * MTN, MOOV, CELTIIS BJ, MOOV TG, TOGOCOM TG, ORANGE SN, MTN CI, MTN
     * CG only, per Feexpay's own docs). The customer must confirm on
     * their phone; this call returns immediately with a reference to
     * poll via status() — it is NOT proof of payment by itself.
     *
     * $reference is a caller-generated correlation ID (e.g. the pending
     * order's ID) — Feexpay's own SDK docblock calls this `custom_id`.
     *
     * A 10s timeout is set explicitly (see post() below) — the vendor
     * SDK's underlying curl call set none at all and was observed to hang
     * past 20s with no response in earlier testing.
     */
    public static function payLocal(
        string $shopId,
        string $apiKey,
        float $amount,
        string $phone,
        string $network,
        string $fullName,
        string $email,
        string $reference,
        bool $sandbox = true,
    ): string|false {
        $data = self::post(self::BASE_URL . '/transactions/requesttopay/integration', $apiKey, [
            'phoneNumber' => $phone,
            'amount' => self::wholeAmount($amount),
            'reseau' => $network,
            'shop' => $shopId,
            'first_name' => $fullName,
            'email' => $email,
            'callback_info' => '',
            'reference' => $reference,
            'otp' => '',
        ]);

        return $data['reference'] ?? false;
    }

    /**
     * Real finding from live testing: Feexpay's API rejects a non-integer
     * `amount` outright ("Validation failed" / "amount must be an integer
     * number") — it wants whole units (XOF has no subunit in practice
     * anyway), not the float this class's public signature accepts for
     * consistency with every other gateway in this package.
     */
    private static function wholeAmount(float $amount): int
    {
        return (int) round($amount);
    }

    /**
     * Returns a hosted payment URL to redirect to (FREE SN, ORANGE CI,
     * MOOV CI, WAVE CI, MOOV BF, ORANGE BF) instead of a direct USSD push.
     *
     * @return array{payment_url: string, reference: string, order_id: string}|false
     */
    public static function payByWebUrl(
        string $shopId,
        string $apiKey,
        float $amount,
        string $phone,
        string $network,
        string $fullName,
        string $email,
        string $reference,
        string $cancelUrl,
        string $returnUrl,
        bool $sandbox = true,
    ): array|false {
        $data = self::post(self::BASE_URL . '/transactions/requesttopay/integration', $apiKey, [
            'phoneNumber' => $phone,
            'amount' => self::wholeAmount($amount),
            'reseau' => $network,
            'shop' => $shopId,
            'first_name' => $fullName,
            'email' => $email,
            'callback_info' => '',
            'reference' => $reference,
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
        ]);

        if ($data === null || ($data['status'] ?? null) === 'FAILED') {
            return false;
        }

        return [
            'payment_url' => $data['payment_url'] ?? '',
            'reference' => $data['reference'] ?? '',
            'order_id' => $data['order_id'] ?? '',
        ];
    }

    /**
     * Real server-to-server status check.
     *
     * @return array{amount: mixed, clientNum: string, status: string, reference: string}|false
     */
    public static function status(string $shopId, string $apiKey, string $reference, bool $sandbox = true): array|false
    {
        $data = self::get(self::BASE_URL . '/transactions/public/single/status/' . rawurlencode($reference), $apiKey);

        if ($data === null) {
            return false;
        }

        return [
            'amount' => $data['amount'] ?? null,
            'clientNum' => $data['phoneNumber'] ?? $data['phone_number'] ?? '',
            'status' => $data['status'] ?? null,
            'reference' => $data['reference'] ?? $reference,
        ];
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>|null
     */
    private static function post(string $url, string $apiKey, array $fields): ?array
    {
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                    . "Accept: application/json\r\n"
                    . "Authorization: Bearer {$apiKey}\r\n",
                'content' => http_build_query($fields),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]));

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function get(string $url, string $apiKey): ?array
    {
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\nAuthorization: Bearer {$apiKey}\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]));

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        return is_array($data) ? $data : null;
    }
}
