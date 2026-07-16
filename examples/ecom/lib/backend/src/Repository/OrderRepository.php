<?php

namespace Backend\Repository;

use Engine\Database\Database;

/**
 * status progresses over time for the StreamBuilder demo on the order
 * confirmation screen: "confirmée" -> "préparée" -> "expédiée" -> "livrée",
 * one step every 15s since order creation — a simulated live tracker with
 * no background worker needed (computed on read, not stored as a timer).
 */
final class OrderRepository
{
    private const STATUSES = ['confirmée', 'en préparation', 'expédiée', 'livrée'];
    private const SECONDS_PER_STEP = 15;

    public function __construct()
    {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        Database::connection()->executeStatement(
            'CREATE TABLE IF NOT EXISTS orders ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'customer_name TEXT NOT NULL, '
            . 'address TEXT NOT NULL, '
            . 'items_json TEXT NOT NULL, '
            . 'total_cents INTEGER NOT NULL, '
            . 'created_at TEXT NOT NULL'
            . ')',
        );
    }

    /**
     * @param array<int, array{name: string, quantity: int, price_cents: int}> $items
     */
    public function create(string $customerName, string $address, array $items, int $totalCents): int
    {
        Database::connection()->insert('orders', [
            'customer_name' => $customerName,
            'address' => $address,
            'items_json' => json_encode($items),
            'total_cents' => $totalCents,
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = Database::connection()->fetchAssociative('SELECT * FROM orders WHERE id = ?', [$id]);

        return $row === false ? null : $row;
    }

    public function status(int $id): ?string
    {
        $order = $this->find($id);
        if ($order === null) {
            return null;
        }

        $createdAt = new \DateTimeImmutable($order['created_at']);
        $elapsed = (new \DateTimeImmutable())->getTimestamp() - $createdAt->getTimestamp();
        $step = min(intdiv($elapsed, self::SECONDS_PER_STEP), count(self::STATUSES) - 1);

        return self::STATUSES[$step];
    }
}
