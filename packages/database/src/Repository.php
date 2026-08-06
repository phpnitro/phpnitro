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

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * A generic base every hand-written Repository in lib/backend/src/
 * Repository/ (VisitRepository, FcmTokenRepository, UserRepository,
 * PasswordResetRepository...) had to reinvent by hand — find-by-id,
 * find-all, simple where(), insert/update/delete, pagination, exists()/
 * count(). Extending this instead of writing raw SQL for every one of
 * those gets them for free; ensureSchema() and anything genuinely
 * bespoke (password hashing, token generation) still belongs in the
 * subclass, same as today.
 *
 * Deliberately NOT a full ORM (no entity hydration/mapping, no
 * UnitOfWork, no migrations) — that's Doctrine ORM territory, a much
 * bigger dependency and a real behavioral shift (identity map, lazy
 * proxies, a mapping layer between your classes and your tables) this
 * framework doesn't currently ask for. This leans on Doctrine DBAL's own
 * QueryBuilder (already a real dependency via Database::connection(),
 * not a new one) for safe, parameterized query construction — arrays in,
 * arrays out, no entity classes required. Reach for Doctrine ORM
 * directly (`composer require doctrine/orm`) instead of this if a
 * project actually needs relations/lazy-loading/a full identity map;
 * nothing here stops that, Database::connection() already hands back a
 * real Doctrine\DBAL\Connection either way.
 *
 * @template T of array<string, mixed>
 */
abstract class Repository
{
    /** The table this repository reads/writes — override in the subclass. */
    abstract protected function table(): string;

    /** The primary key column — override if it isn't "id". */
    protected function primaryKey(): string
    {
        return 'id';
    }

    /** @return array<string, mixed>|null */
    public function find(int|string $id): ?array
    {
        $row = Database::connection()->fetchAssociative(
            'SELECT * FROM ' . $this->table() . ' WHERE ' . $this->primaryKey() . ' = ?',
            [$id],
        );

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function all(?string $orderBy = null, string $direction = 'ASC'): array
    {
        $qb = $this->queryBuilder()->select('*')->from($this->table());
        if ($orderBy !== null) {
            $qb->orderBy($orderBy, $direction);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Equality-only conditions (column => value) ANDed together — enough
     * for the vast majority of real lookups without needing to hand the
     * caller a raw QueryBuilder. Use queryBuilder() directly (see below)
     * for anything more elaborate (LIKE, OR, joins, ordering by an
     * expression).
     *
     * @param array<string, mixed> $conditions
     * @return array<int, array<string, mixed>>
     */
    public function where(array $conditions, ?int $limit = null): array
    {
        $qb = $this->buildWhere($conditions)->select('*')->from($this->table());
        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /** @param array<string, mixed> $conditions @return array<string, mixed>|null */
    public function first(array $conditions): ?array
    {
        $rows = $this->where($conditions, limit: 1);

        return $rows[0] ?? null;
    }

    /** @param array<string, mixed> $conditions */
    public function exists(array $conditions): bool
    {
        return $this->first($conditions) !== null;
    }

    /** @param array<string, mixed> $conditions */
    public function count(array $conditions = []): int
    {
        $qb = $this->buildWhere($conditions)->select('COUNT(*)')->from($this->table());

        return (int) $qb->executeQuery()->fetchOne();
    }

    /**
     * Page numbers are 1-based (page 1 = the first page) — matches how
     * every non-technical product spec/UI counts pages, so a screen's
     * own "?page=" query param can be handed straight in without an
     * off-by-one translation at every call site.
     *
     * @param array<string, mixed> $conditions
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int, totalPages: int}
     */
    public function paginate(int $page, int $perPage, array $conditions = [], ?string $orderBy = null, string $direction = 'ASC'): array
    {
        $page = max(1, $page);
        $total = $this->count($conditions);

        $qb = $this->buildWhere($conditions)->select('*')->from($this->table());
        if ($orderBy !== null) {
            $qb->orderBy($orderBy, $direction);
        }
        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);

        return [
            'items' => $qb->executeQuery()->fetchAllAssociative(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    /** @param array<string, mixed> $data @return int|string the new row's primary key (lastInsertId) */
    public function insert(array $data): int|string
    {
        Database::connection()->insert($this->table(), $data);

        return Database::connection()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int|string $id, array $data): void
    {
        Database::connection()->update($this->table(), $data, [$this->primaryKey() => $id]);
    }

    public function delete(int|string $id): void
    {
        Database::connection()->delete($this->table(), [$this->primaryKey() => $id]);
    }

    /**
     * The escape hatch — a real Doctrine\DBAL\Query\QueryBuilder, already
     * bound to this repository's connection, for anything where(),
     * paginate(), etc. above don't cover (joins, LIKE, OR conditions,
     * GROUP BY...). Doctrine's own documentation covers the full fluent
     * API this returns.
     */
    protected function queryBuilder(): QueryBuilder
    {
        return Database::connection()->createQueryBuilder();
    }

    /** @param array<string, mixed> $conditions */
    private function buildWhere(array $conditions): QueryBuilder
    {
        $qb = $this->queryBuilder();
        foreach ($conditions as $column => $value) {
            $paramName = 'w_' . $column;
            $qb->andWhere("{$column} = :{$paramName}")->setParameter($paramName, $value);
        }

        return $qb;
    }
}
