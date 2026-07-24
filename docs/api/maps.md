# Package `maps`

## `Engine\Maps\GoogleMap` (class)

Real interactive map (pan/zoom/marker) via the Google Maps JavaScript API — needs an API key restricted (by HTTP referrer / package name) in the Google Cloud Console, which is Google's own documented safe way to use it client-side. Implemented against Google's current published API docs; not exercised against a real Google Cloud project in this environment (no key available here) — same honesty-about-confidence rule as the payment gateways in Engine\Payments\.

### `__construct(string $apiKey, float $latitude, float $longitude, int $zoom = 15, string $classes = 'w-full h-64 rounded-xl')`

### `static make(string $apiKey, float $latitude, float $longitude, int $zoom = 15, string $classes = 'w-full h-64 rounded-xl'): self`

### `render(): string`

## `Engine\Maps\MapView` (class)

Picks ONE map provider to render, whichever comes first in this priority list with its key configured in $_ENV — see phpnitro.yml's `maps:` section for the env var names. Same "check $_ENV in priority order" idiom as CheckoutPage::selectPaymentWidget(). No key configured anywhere -> OpenStreetMap (no configuration needed, always available).

### `static make(float $latitude, float $longitude, int $zoom = 15, string $classes = 'w-full h-64 rounded-xl'): Engine\Widget`

## `Engine\Maps\MapboxMap` (class)

Real interactive map (pan/zoom/marker) via Mapbox GL JS v3 — needs a Mapbox access token (safe to expose client-side: that's how Mapbox's own public tokens are meant to be used, unlike a payment gateway's secret key). Implemented against Mapbox's current published API docs; not exercised against a real Mapbox account in this environment (no sandbox token available here) — same honesty-about-confidence rule as the payment gateways in Engine\Payments\.

### `__construct(string $accessToken, float $latitude, float $longitude, int $zoom = 15, string $classes = 'w-full h-64 rounded-xl')`

### `static make(string $accessToken, float $latitude, float $longitude, int $zoom = 15, string $classes = 'w-full h-64 rounded-xl'): self`

### `render(): string`

## `Engine\Maps\OsmMap` (class)

Real interactive map (pan/zoom/marker) via Leaflet.js + OpenStreetMap tiles — no API key, no billing account, works immediately. Unlike the bare iframe embed this replaces, this one is a genuine map widget: same capability tier as MapboxMap/GoogleMap, just with no configuration step.

### `__construct(float $latitude, float $longitude, int $zoom = 15, string $classes = 'w-full h-64 rounded-xl')`

### `static make(float $latitude, float $longitude, int $zoom = 15, string $classes = 'w-full h-64 rounded-xl'): self`

### `render(): string`
