<?php

namespace Engine\Database\Tests;

use Doctrine\DBAL\DriverManager;
use Engine\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    private string $migrationsDir;

    protected function setUp(): void
    {
        $this->migrationsDir = sys_get_temp_dir() . '/phpnitro-migrations-test-' . uniqid();
        mkdir($this->migrationsDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->migrationsDir . '/*.php') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->migrationsDir);
    }

    private function connection(): \Doctrine\DBAL\Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    private function writeMigration(string $filename, string $body): void
    {
        file_put_contents($this->migrationsDir . '/' . $filename, $body);
    }

    public function testMigrateCreatesATableAndTracksIt(): void
    {
        $this->writeMigration('20260101000000_create_posts.php', <<<'PHP'
            <?php
            use Doctrine\DBAL\Connection;
            use Engine\Database\Migration;
            return new class extends Migration {
                public function up(Connection $connection): void
                {
                    $connection->executeStatement('CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT)');
                }
                public function down(Connection $connection): void
                {
                    $connection->executeStatement('DROP TABLE posts');
                }
            };
            PHP);

        $connection = $this->connection();
        $migrator = new Migrator($connection, $this->migrationsDir);

        $ran = $migrator->migrate();

        self::assertSame(['20260101000000_create_posts.php'], $ran);
        $connection->insert('posts', ['id' => 1, 'title' => 'Hello']);
        self::assertSame('Hello', $connection->fetchOne('SELECT title FROM posts WHERE id = 1'));
        self::assertSame([], $migrator->pendingMigrations());
    }

    public function testMigrateIsIdempotent(): void
    {
        $this->writeMigration('20260101000000_create_posts.php', <<<'PHP'
            <?php
            use Doctrine\DBAL\Connection;
            use Engine\Database\Migration;
            return new class extends Migration {
                public function up(Connection $connection): void
                {
                    $connection->executeStatement('CREATE TABLE posts (id INTEGER PRIMARY KEY)');
                }
                public function down(Connection $connection): void
                {
                    $connection->executeStatement('DROP TABLE posts');
                }
            };
            PHP);

        $connection = $this->connection();
        $migrator = new Migrator($connection, $this->migrationsDir);

        $migrator->migrate();
        // Second call must NOT try to re-run (and re-fail on "table already exists").
        $secondRun = $migrator->migrate();

        self::assertSame([], $secondRun);
    }

    public function testRollbackUndoesOnlyTheLastBatch(): void
    {
        $this->writeMigration('20260101000000_create_posts.php', <<<'PHP'
            <?php
            use Doctrine\DBAL\Connection;
            use Engine\Database\Migration;
            return new class extends Migration {
                public function up(Connection $connection): void
                {
                    $connection->executeStatement('CREATE TABLE posts (id INTEGER PRIMARY KEY)');
                }
                public function down(Connection $connection): void
                {
                    $connection->executeStatement('DROP TABLE posts');
                }
            };
            PHP);

        $connection = $this->connection();
        $migrator = new Migrator($connection, $this->migrationsDir);
        $migrator->migrate(); // batch 1

        $this->writeMigration('20260102000000_create_comments.php', <<<'PHP'
            <?php
            use Doctrine\DBAL\Connection;
            use Engine\Database\Migration;
            return new class extends Migration {
                public function up(Connection $connection): void
                {
                    $connection->executeStatement('CREATE TABLE comments (id INTEGER PRIMARY KEY)');
                }
                public function down(Connection $connection): void
                {
                    $connection->executeStatement('DROP TABLE comments');
                }
            };
            PHP);
        $migrator->migrate(); // batch 2, only "comments"

        $rolledBack = $migrator->rollback();

        self::assertSame(['20260102000000_create_comments.php'], $rolledBack);
        // posts (batch 1) must still exist — only the last batch rolls back.
        $connection->executeStatement('SELECT * FROM posts');
        self::assertSame(['20260101000000_create_posts.php'], $migrator->ranMigrations());
        self::assertSame(['20260102000000_create_comments.php'], $migrator->pendingMigrations());
    }

    public function testStatusReportsRanAndPending(): void
    {
        $this->writeMigration('20260101000000_create_posts.php', <<<'PHP'
            <?php
            use Doctrine\DBAL\Connection;
            use Engine\Database\Migration;
            return new class extends Migration {
                public function up(Connection $connection): void
                {
                    $connection->executeStatement('CREATE TABLE posts (id INTEGER PRIMARY KEY)');
                }
                public function down(Connection $connection): void
                {
                }
            };
            PHP);
        $this->writeMigration('20260102000000_create_comments.php', <<<'PHP'
            <?php
            use Doctrine\DBAL\Connection;
            use Engine\Database\Migration;
            return new class extends Migration {
                public function up(Connection $connection): void
                {
                }
                public function down(Connection $connection): void
                {
                }
            };
            PHP);

        $connection = $this->connection();
        $migrator = new Migrator($connection, $this->migrationsDir);
        // Only run the first one, by constructing a fresh Migrator scoped
        // to a dir containing just that file, then re-scanning both.
        $onlyFirstDir = $this->migrationsDir;
        $firstOnlyMigrator = new Migrator($connection, $onlyFirstDir);
        // Simplest real way to leave the second pending: migrate() runs
        // everything found, so temporarily rename the second file out of
        // the way for this first migrate() call.
        rename($this->migrationsDir . '/20260102000000_create_comments.php', $this->migrationsDir . '/20260102000000_create_comments.php.hold');
        $firstOnlyMigrator->migrate();
        rename($this->migrationsDir . '/20260102000000_create_comments.php.hold', $this->migrationsDir . '/20260102000000_create_comments.php');

        $status = $migrator->status();

        self::assertSame([
            ['migration' => '20260101000000_create_posts.php', 'ran' => true],
            ['migration' => '20260102000000_create_comments.php', 'ran' => false],
        ], $status);
    }

    public function testRollbackWithNothingToRollBackIsANoOp(): void
    {
        $migrator = new Migrator($this->connection(), $this->migrationsDir);

        self::assertSame([], $migrator->rollback());
    }
}
