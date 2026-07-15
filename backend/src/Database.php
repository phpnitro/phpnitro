<?php

namespace Backend;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

/**
 * Connection factory. Defaults to a local SQLite file (zero setup) unless
 * DATABASE_URL is set in .env — same DSN switches to MySQL or PostgreSQL,
 * Doctrine DBAL supports all three without any code change.
 */
final class Database
{
    private static ?Connection $connection = null;

    public static function connection(): Connection
    {
        if (self::$connection === null) {
            $url = $_ENV['DATABASE_URL'] ?? 'sqlite:///' . dirname(__DIR__) . '/var/data.sqlite';

            $dsnParser = new DsnParser([
                'sqlite' => 'pdo_sqlite',
                'mysql' => 'pdo_mysql',
                'postgresql' => 'pdo_pgsql',
            ]);

            self::$connection = DriverManager::getConnection($dsnParser->parse($url));
        }

        return self::$connection;
    }
}
