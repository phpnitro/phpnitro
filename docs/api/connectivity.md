# Package `connectivity`

## `Engine\Connectivity\ConnectivityBadge` (class)

connectivity_plus equivalent — a live online/offline indicator. assets/js/connectivity.js paints the real state right after mount (navigator.onLine, native ConnectivityManager when available) and repaints on every browser online/offline event, same "server renders a placeholder, JS keeps it live" idiom as StreamBuilder/FutureBuilder. PHP itself cannot know the client's connectivity synchronously (a page request obviously already reached the server), so there is no server-rendered "real" initial state to show — the placeholder is intentionally generic until JS paints over it on the very next frame.

### `__construct(string $onlineLabel = 'En ligne', string $offlineLabel = 'Hors ligne', string $onlineClasses = 'text-sm text-green-600 dark:text-green-400', string $offlineClasses = 'text-sm text-red-600 dark:text-red-400')`

### `static make(string $onlineLabel = 'En ligne', string $offlineLabel = 'Hors ligne', string $onlineClasses = 'text-sm text-green-600 dark:text-green-400', string $offlineClasses = 'text-sm text-red-600 dark:text-red-400'): self`

### `render(): string`
