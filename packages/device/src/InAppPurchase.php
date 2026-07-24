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

use Engine\Html;
use Engine\Widget;

/**
 * Google Play Billing (one-time products only — no subscriptions, no
 * consumables acknowledgment flow yet). Product IDs must already exist in
 * Play Console under the app's own package; there is no sandbox usable
 * outside a real Play Console account, so this has never run against a
 * real product (see ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md).
 */
final class InAppPurchase
{
    /**
     * @param string[] $productIds
     */
    public static function queryOnClick(array $productIds, string $outputId): string
    {
        return sprintf(
            "phpxDevice.queryProducts(%s, '%s')",
            json_encode(array_values($productIds), JSON_THROW_ON_ERROR),
            $outputId,
        );
    }

    public static function purchaseOnClick(string $productId): string
    {
        return sprintf("phpxDevice.purchaseProduct('%s')", $productId);
    }

    public static function outputElement(string $id, string $classes = 'text-sm text-gray-500 dark:text-gray-400'): Widget
    {
        return Html::raw(sprintf(
            '<span id="%s" class="%s"></span>',
            htmlspecialchars($id, ENT_QUOTES),
            htmlspecialchars($classes, ENT_QUOTES),
        ));
    }
}
