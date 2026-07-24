<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Preferences;

use Engine\Database\Database;

/**
 * Persistent key-value storage, surviving across app restarts — Flutter's
 * shared_preferences, but backed by Engine\Database\Database::connection()
 * (SQLite by default on-device, same DBAL connection everything else in
 * this framework already uses) instead of a platform-specific native API.
 * Values are JSON-encoded, so scalars/arrays/null all round-trip.
 *
 * Deliberately NOT a wrapper around Android SharedPreferences/iOS
 * UserDefaults: those are per-platform native stores this framework would
 * need two separate native bridges for, whereas every other piece of
 * server-side state here (sessions, VisitRepository...) already goes
 * through Database — this stays consistent with that instead of adding a
 * third storage mechanism.
 */
final class Preferences
{
    private static bool $schemaEnsured = false;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::ensureSchema();

        $value = Database::connection()->fetchOne(
            'SELECT pref_value FROM preferences WHERE pref_key = ?',
            [$key],
        );

        return $value === false ? $default : json_decode((string) $value, true);
    }

    public static function set(string $key, mixed $value): void
    {
        self::ensureSchema();

        $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        $connection = Database::connection();

        if (self::has($key)) {
            $connection->executeStatement(
                'UPDATE preferences SET pref_value = ? WHERE pref_key = ?',
                [$encoded, $key],
            );

            return;
        }

        $connection->insert('preferences', ['pref_key' => $key, 'pref_value' => $encoded]);
    }

    public static function has(string $key): bool
    {
        self::ensureSchema();

        return Database::connection()->fetchOne(
            'SELECT 1 FROM preferences WHERE pref_key = ?',
            [$key],
        ) !== false;
    }

    public static function remove(string $key): void
    {
        self::ensureSchema();

        Database::connection()->executeStatement('DELETE FROM preferences WHERE pref_key = ?', [$key]);
    }

    public static function clear(): void
    {
        self::ensureSchema();

        Database::connection()->executeStatement('DELETE FROM preferences');
    }

    /**
     * @return array<string, mixed> every stored key, decoded — used by
     * PageRenderer's DevTools panel to show current preference state, not
     * meant for hot paths (one query, whole table).
     */
    public static function all(): array
    {
        self::ensureSchema();

        $rows = Database::connection()->fetchAllAssociative('SELECT pref_key, pref_value FROM preferences');

        $result = [];
        foreach ($rows as $row) {
            $result[$row['pref_key']] = json_decode((string) $row['pref_value'], true);
        }

        return $result;
    }

    private static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        Database::connection()->executeStatement(
            'CREATE TABLE IF NOT EXISTS preferences (pref_key VARCHAR(255) PRIMARY KEY, pref_value TEXT NOT NULL)',
        );
        self::$schemaEnsured = true;
    }
}
