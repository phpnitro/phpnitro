<?php

namespace Engine\Database\Tests;

use Engine\Database\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(Database::class);
        $reflection->getProperty('connection')->setValue(null, null);
        $reflection->getProperty('sqlitePath')->setValue(null, null);
    }

    public function testConnectsToASqliteFileAndRunsQueries(): void
    {
        $path = sys_get_temp_dir() . '/phpnitro-database-test-' . uniqid() . '.sqlite';
        Database::useSqlitePath($path);

        $connection = Database::connection();
        $connection->executeStatement('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');
        $connection->insert('t', ['id' => 1, 'name' => 'Awa']);

        self::assertSame('Awa', $connection->fetchOne('SELECT name FROM t WHERE id = 1'));

        unlink($path);
    }

    public function testReusesTheSameConnectionAcrossCalls(): void
    {
        $path = sys_get_temp_dir() . '/phpnitro-database-test-' . uniqid() . '.sqlite';
        Database::useSqlitePath($path);

        self::assertSame(Database::connection(), Database::connection());

        unlink($path);
    }
}
