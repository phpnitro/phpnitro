# Package `database`

## `Engine\Database\Database` (class)

Connection factory. Defaults to a local SQLite file (zero setup) unless DATABASE_URL is set in .env — same DSN switches to MySQL or PostgreSQL, Doctrine DBAL supports all three without any code change.

### `static useSqlitePath(string $path): void`

### `static connection(): Doctrine\DBAL\Connection`

## `Engine\Database\Repository` (class)

A generic base every hand-written Repository in lib/backend/src Repository/ (VisitRepository, FcmTokenRepository, UserRepository, PasswordResetRepository...) had to reinvent by hand — find-by-id, find-all, simple where(), insert/update/delete, pagination, exists() count(). Extending this instead of writing raw SQL for every one of those gets them for free; ensureSchema() and anything genuinely bespoke (password hashing, token generation) still belongs in the subclass, same as today.

### `find(string|int $id): ?array`

### `all(?string $orderBy = NULL, string $direction = 'ASC'): array`

### `where(array $conditions, ?int $limit = NULL): array`

Equality-only conditions (column => value) ANDed together — enough for the vast majority of real lookups without needing to hand the caller a raw QueryBuilder. Use queryBuilder() directly (see below) for anything more elaborate (LIKE, OR, joins, ordering by an expression).

### `first(array $conditions): ?array`

### `exists(array $conditions): bool`

### `count(array $conditions = array (
)): int`

### `paginate(int $page, int $perPage, array $conditions = array (
), ?string $orderBy = NULL, string $direction = 'ASC'): array`

Page numbers are 1-based (page 1 = the first page) — matches how every non-technical product spec/UI counts pages, so a screen's own "?page=" query param can be handed straight in without an off-by-one translation at every call site.

### `insert(array $data): string|int`

### `update(string|int $id, array $data): void`

### `delete(string|int $id): void`
