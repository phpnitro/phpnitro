<?php

namespace Backend\Repository;

use Engine\Database\Database;

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
            . 'name TEXT NOT NULL, '
            . 'email TEXT NOT NULL UNIQUE, '
            . 'password_hash TEXT NOT NULL, '
            . 'created_at TEXT NOT NULL'
            . ')',
        );
    }

    public function emailExists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function create(string $name, string $email, string $password): int
    {
        Database::connection()->insert('users', [
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $row = Database::connection()->fetchAssociative('SELECT * FROM users WHERE email = ?', [$email]);

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = Database::connection()->fetchAssociative('SELECT * FROM users WHERE id = ?', [$id]);

        return $row === false ? null : $row;
    }

    public function verifyPassword(array $user, string $password): bool
    {
        return password_verify($password, $user['password_hash']);
    }
}
