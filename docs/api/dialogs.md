# Package `dialogs`

## `Engine\Dialogs\AlertButton` (class)

### `__construct(string $message, string $label = 'Afficher un message', string $title = '', string $classes = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium px-4 py-2 rounded-lg')`

### `static make(string $message, string $label = 'Afficher un message', string $title = '', string $classes = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium px-4 py-2 rounded-lg'): self`

### `render(): string`

## `Engine\Dialogs\ConfirmButton` (class)

Shows a confirmation dialog; only submits `action` (via phpxNav, no full page reload) if the user confirms — same idiom as Engine\Device\'s onClick triggers (a plain button calling a JS bridge), not a <form> submit, since the server call must not happen until the user actually confirms.

### `__construct(string $message, string $action, string $label = 'Confirmer', string $title = '', string $classes = 'bg-red-600 text-white font-medium px-4 py-2 rounded-lg')`

### `static make(string $message, string $action, string $label = 'Confirmer', string $title = '', string $classes = 'bg-red-600 text-white font-medium px-4 py-2 rounded-lg'): self`

### `render(): string`
