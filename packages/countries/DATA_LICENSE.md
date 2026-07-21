# Data sources and licenses

The **code** in this package (`src/*.php`) is MIT-licensed, same as the rest
of PhpNitro (see the repository root `LICENSE`). The **data** embedded in
`CountryData.php` and `CityData.php` is derived from two separately-licensed
open datasets, credited here as required by their own licenses:

- **Country facts** (name, capital, continent/region, calling code,
  currency): [mledoze/countries](https://github.com/mledoze/countries),
  licensed under the [Open Data Commons Open Database License (ODbL)
  v1.0](https://opendatacommons.org/licenses/odbl/1-0/). Filtered to the 194
  entries flagged `unMember` or `independent` in the source dataset.
- **Cities** (up to 15 largest per country by population):
  [GeoNames](https://www.geonames.org/) `cities15000` dump, licensed under
  [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). Filtered from
  ~34,000 entries (every city with population > 15,000) down to the 15
  largest per country to keep the embedded size reasonable for a mobile
  bundle — not an exhaustive world city gazetteer.

If you redistribute a database built from this package's data (not just an
app that uses it), check whether your use still satisfies ODbL's
attribution/share-alike terms for the country data.
