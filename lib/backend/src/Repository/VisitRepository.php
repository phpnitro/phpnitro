<?php

namespace Backend\Repository;

use Engine\Database\Database;
use Engine\Database\Repository;

/** Refactored onto the generic Engine\Database\Repository base — insert()/count() below are its own, ensureSchema() is the only bespoke bit left. */
final class VisitRepository extends Repository
{
    public function __construct()
    {
        $this->ensureSchema();
    }

    protected function table(): string
    {
        return 'visits';
    }

    private function ensureSchema(): void
    {
        Database::connection()->executeStatement(
            'CREATE TABLE IF NOT EXISTS visits (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL)',
        );
    }

    public function recordVisit(): void
    {
        $this->insert(['created_at' => (new \DateTimeImmutable())->format(DATE_ATOM)]);
    }

    public function countVisits(): int
    {
        return $this->count();
    }
}
