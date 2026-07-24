# Package `countries`

## `Engine\Countries\CityData` (class)

Up to 15 largest cities per country by population — derived from GeoNames' cities15000 dump (CC BY 4.0, see packages/countries/DATA_LICENSE), filtered and trimmed for embedded/offline size. NOT exhaustive: a full world city gazetteer (GeoNames alone has 4M+ entries) is out of scope for a bundled mobile package — this covers the cities anyone would actually expect to see for city-picker/autocomplete use cases, not a complete gazetteer.

### `static byCountry(): array`

## `Engine\Countries\Continent` (enum)

### `label(): string`

### `static cases(): array`

### `static from(string|int $value): static`

### `static tryFrom(string|int $value): ?static`

## `Engine\Countries\Countries` (class)

Offline world country/city data — 194 UN member/independent states, no network call, no API key (see packages/countries/DATA_LICENSE for the two open datasets this is built from). Flutter's countries-style packages usually wrap a remote API or a bundled JSON asset parsed at runtime; here the data is plain PHP arrays (CountryData/CityData), already in the exact shape autoloading needs — no parse step, no asset file to read from disk.

### `static all(): array`

### `static find(string $code): ?Engine\Countries\Country`

Accepts either the ISO 3166-1 alpha-2 ("FR") or alpha-3 ("FRA") code, case-insensitively.

### `static search(string $query): array`

Case-insensitive substring match against both the English and French common names.

### `static byContinent(Engine\Countries\Continent $continent): array`

## `Engine\Countries\Country` (class)

A single country's facts. Never constructed directly by consumers — get instances from Countries::all()/find()/search()/byContinent().

### `__construct(string $code, string $code3, string $name, string $nameFr, ?string $capital, Engine\Countries\Continent $continent, string $callingCode, string $currency)`

### `flag(): string`

Regional indicator symbols computed from the ISO code, not a stored lookup table — always correct by construction (any two-letter code maps to a flag the same way), nothing to keep in sync. Renders as an emoji flag on any font/OS with regional indicator support (all modern Android/iOS WebViews).

### `cities(): array`

Up to 15 largest cities by population, largest first — always includes the capital when GeoNames tracked it above the 15,000 population threshold this data was filtered at (see CityData). Falls back to just the capital when the country has no separate city data (a handful of small island nations aren't in the GeoNames extract).

## `Engine\Countries\CountryData` (class)

Country facts (name, capital, continent, calling code, currency) — derived from the mledoze/countries open dataset (ODbL-licensed, see packages/countries/DATA_LICENSE), 194 UN member / independent states. Flag is computed from the ISO 3166-1 alpha-2 code (regional indicator symbols), not stored data — always correct by construction, no lookup table to keep in sync.

### `static all(): array`
