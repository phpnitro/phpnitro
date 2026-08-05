# Package `analytics`

## `Engine\Analytics\Analytics` (class)

Local, dependency-free event tracking — no Firebase Analytics/Mixpanel account needed to know which screens/actions an app's own users actually touch. Same SQLite-backed pattern as Engine\Preferences\Preferences (Database::connection() is already a real dependency everywhere in this framework, not a new one) — events land in a plain table on-device, queryable with summary()/recent() for a developer's own debug screen or export flow, same "stays local until someone deliberately does something with it" philosophy as CrashReporter.kt on the Kotlin side.

### `static track(string $event, array $properties = array (
)): void`

### `static recent(int $limit = 50): array`

Most recent first.

### `static count(string $event): int`

### `static summary(): array`

### `static clear(): void`
