<?php

namespace Backend\Repository;

use Backend\Database;

final class VisitRepository
{
    public function __construct()
    {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        Database::connection()->executeStatement(
            'CREATE TABLE IF NOT EXISTS visits (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL)',
        );
    }

    public function recordVisit(): void
    {
        Database::connection()->insert('visits', [
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    public function countVisits(): int
    {
        return (int) Database::connection()->fetchOne('SELECT COUNT(*) FROM visits');
    }
}
