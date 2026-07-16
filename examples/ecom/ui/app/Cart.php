<?php

namespace Engine\App;

/**
 * Session-backed cart, shared across screens (unlike Screen's own state,
 * which is scoped per class+params — the cart needs to be the same object
 * whether you're on HomePage, ProductPage, or CartPage).
 */
final class Cart
{
    public static function add(int $productId, int $quantity = 1): void
    {
        $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + $quantity;
    }

    public static function remove(int $productId): void
    {
        unset($_SESSION['cart'][$productId]);
    }

    /**
     * @return array<int, int> product id => quantity
     */
    public static function items(): array
    {
        return $_SESSION['cart'] ?? [];
    }

    public static function clear(): void
    {
        unset($_SESSION['cart']);
    }

    public static function count(): int
    {
        return array_sum(self::items());
    }
}
