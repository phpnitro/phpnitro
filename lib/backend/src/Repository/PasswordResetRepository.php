<?php

namespace Backend\Repository;

use Engine\Database\Database;

/**
 * Only the token's HASH is ever stored (same reasoning as password_hash()
 * on the user's own password — a leaked DB row shouldn't hand out a
 * usable reset link), so createToken() is the only place that ever sees
 * the raw token; everything else works from a hash lookup. No real
 * mailer is configured by default anywhere in this framework yet, so
 * NativeForgotPasswordScreen shows the raw link directly on-screen
 * instead of "sending" it — same honest-degradation pattern as
 * FIREBASE_WEB_API_KEY/FEEXPAY_SHOP_ID's "not configured" messages
 * elsewhere, just for a capability with no config step to point at at
 * all (there's no SMTP_* to set — wire a real mailer call where
 * NativeForgotPasswordScreen's docblock says to, once one exists).
 */
final class PasswordResetRepository
{
    private const TTL_SECONDS = 3600;

    public function __construct()
    {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        Database::connection()->executeStatement(
            'CREATE TABLE IF NOT EXISTS password_resets ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'user_id INTEGER NOT NULL, '
            . 'token_hash TEXT NOT NULL, '
            . 'expires_at TEXT NOT NULL, '
            . 'used INTEGER NOT NULL DEFAULT 0'
            . ')',
        );
    }

    /** @return string the RAW token — embed it in the reset link, never store it yourself */
    public function createToken(int $userId): string
    {
        $rawToken = bin2hex(random_bytes(32));

        Database::connection()->insert('password_resets', [
            'user_id' => $userId,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => (new \DateTimeImmutable('+' . self::TTL_SECONDS . ' seconds'))->format(DATE_ATOM),
            'used' => 0,
        ]);

        return $rawToken;
    }

    /** Null if the token is unknown, expired, or already used — caller shows one generic "invalid or expired link" either way. */
    public function findValidUserId(string $rawToken): ?int
    {
        $row = Database::connection()->fetchAssociative(
            'SELECT user_id, expires_at, used FROM password_resets WHERE token_hash = ? ORDER BY id DESC LIMIT 1',
            [hash('sha256', $rawToken)],
        );

        if ($row === false || (int) $row['used'] === 1) {
            return null;
        }
        if (new \DateTimeImmutable($row['expires_at']) < new \DateTimeImmutable()) {
            return null;
        }

        return (int) $row['user_id'];
    }

    public function markUsed(string $rawToken): void
    {
        Database::connection()->update(
            'password_resets',
            ['used' => 1],
            ['token_hash' => hash('sha256', $rawToken)],
        );
    }
}
