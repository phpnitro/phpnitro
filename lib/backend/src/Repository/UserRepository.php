<?php

namespace Backend\Repository;

use Engine\Database\Database;

/**
 * Real accounts, backed by the same SQLite-by-default connection every
 * other repository in this directory uses — replaces the login screen's
 * previous hardcoded `$username === 'demo' && $password === 'demo'`
 * string comparison with an actual password_hash()/password_verify()
 * check against a real table. ensureSchema() seeds a demo/demo row once
 * (only if the table is empty) so the documented "demo / demo" login
 * still works out of the box on a fresh clone — it just now goes through
 * the real hashing path instead of being special-cased.
 */
final class UserRepository
{
    public function __construct()
    {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        Database::connection()->executeStatement(
            'CREATE TABLE IF NOT EXISTS users ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'username TEXT NOT NULL UNIQUE, '
            . 'password_hash TEXT NOT NULL, '
            . 'created_at TEXT NOT NULL'
            . ')',
        );

        $count = (int) Database::connection()->fetchOne('SELECT COUNT(*) FROM users');
        if ($count === 0) {
            $this->create('demo', 'demo');
        }
    }

    /** @return array{id: int, username: string}|null */
    public function verifyCredentials(string $username, string $password): ?array
    {
        $row = Database::connection()->fetchAssociative(
            'SELECT id, username, password_hash FROM users WHERE username = ?',
            [$username],
        );
        if ($row === false || !password_verify($password, $row['password_hash'])) {
            return null;
        }

        return ['id' => (int) $row['id'], 'username' => $row['username']];
    }

    public function usernameTaken(string $username): bool
    {
        return Database::connection()->fetchOne('SELECT 1 FROM users WHERE username = ?', [$username]) !== false;
    }

    /** @return array{id: int, username: string}|null */
    public function findByUsername(string $username): ?array
    {
        $row = Database::connection()->fetchAssociative('SELECT id, username FROM users WHERE username = ?', [$username]);

        return $row === false ? null : ['id' => (int) $row['id'], 'username' => $row['username']];
    }

    /** Used by PasswordResetRepository's flow once a token has been verified — see NativeResetPasswordScreen. */
    public function updatePassword(int $userId, string $newPassword): void
    {
        Database::connection()->update(
            'users',
            ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)],
            ['id' => $userId],
        );
    }

    /** @return array{id: int, username: string} */
    public function create(string $username, string $password): array
    {
        Database::connection()->insert('users', [
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return ['id' => (int) Database::connection()->lastInsertId(), 'username' => $username];
    }
}
