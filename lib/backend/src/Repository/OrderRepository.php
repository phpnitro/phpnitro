<?php

namespace Backend\Repository;

use Engine\Database\Database;

/**
 * A pending/settled payment attempt, one real row per Feexpay reference —
 * see docs/payments.md's own security note: a client-side "success" event
 * is never proof of payment, so this table exists specifically so
 * status() (a server-to-server call, see Engine\Payments\Feexpay) has
 * something durable to update once Feexpay actually confirms it, instead
 * of trusting whatever the phone says.
 */
final class OrderRepository
{
    public function __construct()
    {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        Database::connection()->executeStatement(
            'CREATE TABLE IF NOT EXISTS orders ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'reference TEXT NOT NULL UNIQUE, '
            . 'amount INTEGER NOT NULL, '
            . 'phone TEXT NOT NULL, '
            . 'network TEXT NOT NULL, '
            . 'status TEXT NOT NULL, '
            . 'created_at TEXT NOT NULL'
            . ')',
        );
    }

    public function create(string $reference, int $amount, string $phone, string $network): void
    {
        Database::connection()->insert('orders', [
            'reference' => $reference,
            'amount' => $amount,
            'phone' => $phone,
            'network' => $network,
            'status' => 'PENDING',
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    public function updateStatus(string $reference, string $status): void
    {
        Database::connection()->update('orders', ['status' => $status], ['reference' => $reference]);
    }

    /** @return array{id: int, reference: string, amount: int, phone: string, network: string, status: string}|null */
    public function find(string $reference): ?array
    {
        $row = Database::connection()->fetchAssociative(
            'SELECT id, reference, amount, phone, network, status FROM orders WHERE reference = ?',
            [$reference],
        );

        return $row === false ? null : [
            'id' => (int) $row['id'],
            'reference' => $row['reference'],
            'amount' => (int) $row['amount'],
            'phone' => $row['phone'],
            'network' => $row['network'],
            'status' => $row['status'],
        ];
    }
}
