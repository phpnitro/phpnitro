# Package `preferences`

## `Engine\Preferences\Preferences` (class)

Persistent key-value storage, surviving across app restarts — Flutter's shared_preferences, but backed by Engine\Database\Database::connection() (SQLite by default on-device, same DBAL connection everything else in this framework already uses) instead of a platform-specific native API. Values are JSON-encoded, so scalars/arrays/null all round-trip.

### `static get(string $key, mixed $default = NULL): mixed`

### `static set(string $key, mixed $value): void`

### `static has(string $key): bool`

### `static remove(string $key): void`

### `static clear(): void`

### `static all(): array`

PageRenderer's DevTools panel to show current preference state, not meant for hot paths (one query, whole table).
