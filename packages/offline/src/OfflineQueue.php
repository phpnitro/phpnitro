<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Offline;

use Engine\Database\Database;

/**
 * A persisted queue of mutations made while offline, replayed once
 * connectivity comes back — the piece Engine\Preferences/Engine\State
 * don't cover: both are real local storage, but neither has an opinion
 * about "the user tapped Save while offline, remember it, send it once
 * we're back." SQLite-backed (same Database::connection() every other
 * persisted thing here already uses), so a queued mutation survives the
 * app being killed, not just backgrounded.
 *
 * A typical screen: check Engine\Device\Connectivity::result() (or just
 * try the real call and catch failure); if offline, enqueue() instead of
 * sending; call flush() with a real sender on the next
 * "device:connectivity" success, or unconditionally on app launch — a
 * flush() attempt when nothing's actually reachable just re-queues
 * whatever failed again, it's always safe to call.
 *
 * $action is an opaque string YOUR code assigns meaning to (a Cloud
 * Function name, a REST endpoint, a Supabase table) — this class has no
 * opinion about what "sending" a mutation means, that's $sender's job
 * (see flush()).
 */
final class OfflineQueue
{
    private static bool $schemaEnsured = false;

    /**
     * @param array<string, mixed> $payload
     */
    public static function enqueue(string $action, array $payload): void
    {
        self::ensureSchema();

        Database::connection()->insert('offline_queue', [
            'action' => $action,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => date('c'),
        ]);
    }

    /**
     * @return array<int, array{id: int, action: string, payload: array<string, mixed>, createdAt: string}>
     */
    public static function pending(): array
    {
        self::ensureSchema();

        $rows = Database::connection()->fetchAllAssociative('SELECT * FROM offline_queue ORDER BY id ASC');

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'action' => $row['action'],
                'payload' => json_decode((string) $row['payload'], true),
                'createdAt' => $row['created_at'],
            ],
            $rows,
        );
    }

    public static function count(): int
    {
        self::ensureSchema();

        return (int) Database::connection()->fetchOne('SELECT COUNT(*) FROM offline_queue');
    }

    /**
     * Attempts every pending mutation IN ORDER via $sender (return true
     * on success, false to leave it queued for the next flush()) —
     * stops at the first failure rather than reordering around it, so a
     * later mutation that depends on an earlier one succeeding first
     * (e.g. "create the record" then "update the record") never runs
     * out of order.
     *
     * @param callable(string $action, array<string, mixed> $payload): bool $sender
     * @return array{sent: int, remaining: int}
     */
    public static function flush(callable $sender): array
    {
        self::ensureSchema();

        $sent = 0;
        foreach (self::pending() as $item) {
            if (!$sender($item['action'], $item['payload'])) {
                break;
            }

            Database::connection()->delete('offline_queue', ['id' => $item['id']]);
            $sent++;
        }

        return ['sent' => $sent, 'remaining' => self::count()];
    }

    public static function clear(): void
    {
        self::ensureSchema();

        Database::connection()->executeStatement('DELETE FROM offline_queue');
    }

    private static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        Database::connection()->executeStatement(
            'CREATE TABLE IF NOT EXISTS offline_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                action VARCHAR(255) NOT NULL,
                payload TEXT NOT NULL,
                created_at VARCHAR(32) NOT NULL
            )',
        );
        self::$schemaEnsured = true;
    }
}
