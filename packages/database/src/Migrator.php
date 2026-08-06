<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Database;

use Doctrine\DBAL\Connection;

/**
 * Runs the *.php files under a project's lib/backend/migrations/ —
 * each one `return`s a Migration instance directly (an anonymous class,
 * see `phpx make:migration`'s generated stub) rather than this class
 * having to derive a class name from a filename and `new` it, the same
 * "the file already IS the thing, don't reinvent PSR-4 by hand" idea
 * bin/phpx's own scaffolding uses elsewhere.
 *
 * Tracks what's already run in a `phpnitro_migrations` table (prefixed
 * to stay out of a project's own table namespace) — filename, batch
 * number, timestamp. A "batch" is everything applied by one migrate()
 * call; rollback() only ever undoes the MOST RECENT batch, never a
 * specific migration picked out of the middle of history — the same
 * "undo the last thing you did" scope every migration tool this pattern
 * is modeled on (Laravel, Rails) settles on, rather than letting a
 * project get into an inconsistent partial state by rolling back
 * something with later migrations still depending on it.
 */
final class Migrator
{
    private const TABLE = 'phpnitro_migrations';

    public function __construct(
        private readonly Connection $connection,
        private readonly string $migrationsDir,
    ) {
    }

    /** @return string[] every migration filename, oldest first (relies on the timestamp prefix `make:migration` generates for correct ordering). */
    public function allMigrationFiles(): array
    {
        $files = glob(rtrim($this->migrationsDir, '/') . '/*.php') ?: [];
        $names = array_map(static fn (string $path): string => basename($path), $files);
        sort($names);

        return $names;
    }

    /** @return string[] */
    public function ranMigrations(): array
    {
        $this->ensureMigrationsTable();

        return $this->connection->fetchFirstColumn('SELECT migration FROM ' . self::TABLE . ' ORDER BY id ASC');
    }

    /** @return string[] */
    public function pendingMigrations(): array
    {
        return array_values(array_diff($this->allMigrationFiles(), $this->ranMigrations()));
    }

    /**
     * Runs every pending migration in one new batch.
     *
     * @return string[] the migrations that were actually run, in order (empty if already up to date)
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();
        $pending = $this->pendingMigrations();
        if ($pending === []) {
            return [];
        }

        $batch = $this->nextBatchNumber();
        foreach ($pending as $file) {
            $migration = $this->load($file);
            $migration->up($this->connection);
            $this->connection->insert(self::TABLE, [
                'migration' => $file,
                'batch' => $batch,
                'run_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $pending;
    }

    /**
     * Undoes every migration in the most recent batch, most-recently-run
     * first (the reverse of the order they were applied in — a table a
     * later migration in the same batch depends on shouldn't be dropped
     * before that later migration's own down() has had a chance to run).
     *
     * @return string[] the migrations that were rolled back, in the order down() was called on them (empty if nothing to roll back)
     */
    public function rollback(): array
    {
        $this->ensureMigrationsTable();
        $lastBatch = $this->connection->fetchOne('SELECT MAX(batch) FROM ' . self::TABLE);
        if ($lastBatch === null || $lastBatch === false) {
            return [];
        }

        $toRollback = $this->connection->fetchFirstColumn(
            'SELECT migration FROM ' . self::TABLE . ' WHERE batch = ? ORDER BY id DESC',
            [$lastBatch],
        );

        foreach ($toRollback as $file) {
            $migration = $this->load($file);
            $migration->down($this->connection);
            $this->connection->delete(self::TABLE, ['migration' => $file]);
        }

        return $toRollback;
    }

    /** @return array<int, array{migration: string, ran: bool}> every migration file, in order, with whether it's already been applied. */
    public function status(): array
    {
        $ran = array_flip($this->ranMigrations());

        return array_map(
            static fn (string $file): array => ['migration' => $file, 'ran' => isset($ran[$file])],
            $this->allMigrationFiles(),
        );
    }

    private function load(string $file): Migration
    {
        $migration = require rtrim($this->migrationsDir, '/') . '/' . $file;
        if (!$migration instanceof Migration) {
            throw new \RuntimeException("{$file} ne retourne pas une instance de Migration — voir le stub généré par `phpx make:migration`.");
        }

        return $migration;
    }

    private function nextBatchNumber(): int
    {
        $max = $this->connection->fetchOne('SELECT MAX(batch) FROM ' . self::TABLE);

        return $max === null || $max === false ? 1 : ((int) $max + 1);
    }

    private function ensureMigrationsTable(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if ($schemaManager->tablesExist([self::TABLE])) {
            return;
        }

        $schema = new \Doctrine\DBAL\Schema\Schema();
        $table = $schema->createTable(self::TABLE);
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('migration', 'string', ['length' => 255]);
        $table->addColumn('batch', 'integer');
        $table->addColumn('run_at', 'string', ['length' => 32]);
        $table->setPrimaryKey(['id']);

        foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }
}
