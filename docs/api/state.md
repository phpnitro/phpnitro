# Package `state`

## `Engine\State\Store` (class)

A typed, namespaced API over $_SESSION for state that needs to survive across screens within one login session but doesn't belong in a real database table (a wizard's in-progress answers, a filter the user picked on one screen and expects to still be set on another, a draft). This is this framework's answer to "where do I put shared state" without reaching for a Provider/Bloc-style dependency-injected store — there is no persistent object graph to inject INTO, since every request is a fresh PHP process (see docs/architecture.md's "Le cycle" section); the only thing that actually survives between requests is $_SESSION itself, so this is a thin, safer API over that, not a different mechanism.

### `static get(string $key, mixed $default = NULL): mixed`

### `static set(string $key, mixed $value): void`

### `static has(string $key): bool`

### `static remove(string $key): void`

### `static update(string $key, callable $updater, mixed $default = NULL): mixed`

Read-modify-write in one call — the exact "increment a counter", "toggle a flag", "append to a list" boilerplate every screen that touched $_SESSION/Preferences directly used to hand-roll (see NativeHomeScreen's own counter before this existed: get, cast, add one, set, four lines for one idea). $updater receives the current value (or $default if unset) and returns the new one.

### `static clear(): void`

Clears every key this class has ever set — not the whole $_SESSION, just this namespace.
