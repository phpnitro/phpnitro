<?php

namespace Backend\Repository;

use Engine\Database\Database;

/**
 * Stores device push tokens (Firebase Cloud Messaging) so a backend job can
 * later send notifications to them. Registration itself (getting a token
 * on-device) requires a real Firebase project — see
 * android/app/src/main/java/com/mobile/engine/FcmService.kt.example.
 */
final class FcmTokenRepository
{
    public function __construct()
    {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        Database::connection()->executeStatement(
            'CREATE TABLE IF NOT EXISTS fcm_tokens ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'token TEXT NOT NULL UNIQUE, '
            . 'registered_at TEXT NOT NULL'
            . ')',
        );
    }

    public function register(string $token): void
    {
        Database::connection()->executeStatement(
            'INSERT OR REPLACE INTO fcm_tokens (token, registered_at) VALUES (?, ?)',
            [$token, (new \DateTimeImmutable())->format(DATE_ATOM)],
        );
    }

    /**
     * @return string[]
     */
    public function allTokens(): array
    {
        return Database::connection()->fetchFirstColumn('SELECT token FROM fcm_tokens');
    }
}
