<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Device;

/**
 * Google Play Billing — queries/purchases one-time (INAPP) products (see
 * NativeDeviceBridge.kt's queryProducts()/purchaseProduct(), Billing
 * Library v7) — an action-string builder, not a widget: attach
 * InAppPurchase::queryAction()/InAppPurchase::purchaseAction() to any
 * Button.
 *
 * Written to follow the real Billing Library v7 flow, but never
 * exercised against a real Play Console product from this pipeline (no
 * sandbox reachable outside a real Play Console account with published
 * in-app products) — same documented caveat as
 * NativeDeviceBridge.kt's own billingClient docblock.
 *
 * $productId must already exist as a real in-app product in Play
 * Console for either call to return anything meaningful.
 * purchaseAction() has no result field — a successful purchase is
 * reported through Billing's own PurchasesUpdatedListener, not wired
 * back into this request/response cycle here (see
 * NativeDeviceBridge.kt's billingClient listener — currently a no-op —
 * before relying on this in production).
 */
final class InAppPurchase
{
    public static function queryAction(string $productId, string $outputField = 'iap_out'): string
    {
        return 'device:iapquery:' . rawurlencode($productId) . ":{$outputField}";
    }

    public static function purchaseAction(string $productId): string
    {
        return 'device:iappurchase:' . rawurlencode($productId);
    }

    public static function result(string $outputField = 'iap_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
