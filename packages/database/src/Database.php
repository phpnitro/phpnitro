<?php

namespace Engine\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Tools\DsnParser;

/**
 * Connection factory. Defaults to a local SQLite file (zero setup) unless
 * DATABASE_URL is set in .env — same DSN switches to MySQL or PostgreSQL,
 * Doctrine DBAL supports all three without any code change.
 *
 * Retries the initial connection a few times (mobile networks and remote
 * DB hosts can hiccup transiently) and transparently reconnects once if a
 * previously-open connection turns out to have been dropped mid-session —
 * a caller just gets a working Connection either way instead of a raw PDO
 * "server has gone away" exception.
 *
 * This package doesn't know the consuming app's directory layout, so the
 * default SQLite path is relative to getcwd() unless the app pins it
 * explicitly via useSqlitePath() at boot (see Backend\Kernel).
 */
final class Database
{
    private const MAX_CONNECT_ATTEMPTS = 3;
    private const RETRY_DELAY_MS = 200;

    private static ?Connection $connection = null;
    private static ?string $sqlitePath = null;

    public static function useSqlitePath(string $path): void
    {
        self::$sqlitePath = $path;
    }

    public static function connection(): Connection
    {
        if (self::$connection === null) {
            self::$connection = self::connectWithRetry();

            return self::$connection;
        }

        // connect() already proved the connection alive just above when it
        // was first opened — only worth re-checking on a *reused* instance,
        // to catch a connection dropped mid-session (idle timeout on a
        // remote DB, etc.), not on every single call.
        if (!self::isAlive(self::$connection)) {
            self::$connection = self::connectWithRetry();
        }

        return self::$connection;
    }

    private static function connectWithRetry(): Connection
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_CONNECT_ATTEMPTS; $attempt++) {
            try {
                return self::connect();
            } catch (DbalException $e) {
                $lastError = $e;
                if ($attempt < self::MAX_CONNECT_ATTEMPTS) {
                    usleep(self::RETRY_DELAY_MS * 1000 * $attempt);
                }
            }
        }

        throw new \RuntimeException(
            'Unable to connect to the database after ' . self::MAX_CONNECT_ATTEMPTS . ' attempts: '
            . $lastError?->getMessage(),
            previous: $lastError,
        );
    }

    private static function connect(): Connection
    {
        $path = self::$sqlitePath ?? getcwd() . '/var/data.sqlite';
        $url = $_ENV['DATABASE_URL'] ?? 'sqlite:///' . $path;

        if (!isset($_ENV['DATABASE_URL']) && !is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $dsnParser = new DsnParser([
            'sqlite' => 'pdo_sqlite',
            'mysql' => 'pdo_mysql',
            'postgresql' => 'pdo_pgsql',
        ]);

        $connection = DriverManager::getConnection($dsnParser->parse($url));
        $connection->executeQuery('SELECT 1');

        return $connection;
    }

    private static function isAlive(Connection $connection): bool
    {
        try {
            $connection->executeQuery('SELECT 1');

            return true;
        } catch (DbalException) {
            return false;
        }
    }
}
