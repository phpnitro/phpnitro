# Package `database`

## `Engine\Database\Database` (class)

Connection factory. Defaults to a local SQLite file (zero setup) unless DATABASE_URL is set in .env — same DSN switches to MySQL or PostgreSQL, Doctrine DBAL supports all three without any code change.

### `static useSqlitePath(string $path): void`

### `static connection(): Doctrine\DBAL\Connection`
