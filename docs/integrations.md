# Intégrations

## Cartes

`packages/maps/` (le widget HTML `Maps\MapView`/`OsmMap`/`MapboxMap`/`GoogleMap`) a été supprimé une fois remplacé par un vrai widget natif : `Engine\Native\MapView`, une véritable `org.osmdroid.views.MapView` overlayée au tap (tuiles OpenStreetMap réelles, pan/zoom natif, aucune clé API). Voir [docs/widgets.md](widgets.md).

## Firebase

`packages/firebase/src/` (namespace `Engine\Firebase\`) — aucune dépendance `kreait/firebase-php`/`google/apiclient` : uniquement du REST + `openssl_*`, même philosophie que `StripeCheckout`.

| Classe | Rôle | Authentification |
|---|---|---|
| `GoogleServiceAccount` | Jeton OAuth2 depuis un compte de service (JWT RS256 signé via `openssl_sign`) — brique partagée par les deux classes suivantes. | Compte de service (JSON). |
| `FirebaseMessaging::send(...)` | Envoie une notification push (FCM HTTP v1). **Doit tourner sur ton propre serveur hébergé**, jamais depuis le PHP embarqué sur le téléphone. | Compte de service. |
| `FirebaseAuth::signIn(...)` / `::signUp(...)` | Connexion/inscription via l'API REST Identity Toolkit. | Clé API web (client-safe). |
| `Firestore::get(...)` / `::set(...)` | Client REST minimal (un document à la fois). | Compte de service. |

Confiance : implémenté d'après la doc officielle, rien testé contre un vrai projet Firebase (aucun compte disponible). Le JWT signing a un test dédié qui vérifie la structure et la signature RS256 avec une paire de clés générée à la volée, sans réseau.

## Countries — données pays/villes offline

`packages/countries/src/` (namespace `Engine\Countries\`) — 194 états membres de l'ONU/indépendants, **entièrement offline**, aucune clé API, aucun appel réseau :

```php
$france = Countries::find('FR');           // ou 'FRA' (alpha-3), insensible à la casse
$france->flag();                            // 🇫🇷 — calculé depuis le code ISO, pas stocké
$france->capital;                            // Paris
$france->cities();                           // jusqu'à 15 plus grandes villes par population
$france->continent;                          // Continent::EUROPE
$france->callingCode;                        // +33
$france->currency;                           // EUR

Countries::search('allemagne');              // recherche FR/EN insensible à la casse
Countries::byContinent(Continent::AFRICA);   // filtre par continent
```

Données dérivées de deux jeux de données ouverts, licences documentées dans `packages/countries/DATA_LICENSE.md` : [mledoze/countries](https://github.com/mledoze/countries) (ODbL) pour les faits pays, [GeoNames](https://www.geonames.org/) `cities15000` (CC BY 4.0) pour les villes — pas une base de données mondiale exhaustive (GeoNames seul a 4M+ entrées), juste les 15 plus grandes villes par pays.

## Authentification sociale

`packages/socialauth/` (7 fournisseurs OAuth2 — Google, Microsoft, GitHub, Slack, Facebook, X, Apple) a été supprimé : c'était un service attaché à `Button::make()`, le widget Tailwind supprimé avec la bascule vers le rendu natif, et il n'avait plus aucun appelant. Pas encore reconstruit en natif — un vrai chantier futur (flux OAuth2 déclenché depuis un `Button`, callback géré côté PHP comme avant) plutôt qu'une résurrection de l'ancien code.

## Formats (nombres, devises, dates)

`packages/format/src/Format.php` (namespace `Engine\Format\`) — équivalent `intl`, délibérément **sans dépendre de `ext-intl`** (présence non vérifiée dans le PHP cross-compilé pour Android) :

```php
Format::number(1234.5, 2);                 // "1 234,50"
Format::currency(1234.5, 'EUR');           // "1 234,50 €"
Format::date(new DateTimeImmutable(), 'd MMMM yyyy');  // "21 juillet 2026"
Format::relativeTime($date, locale: 'fr'); // "il y a 3 heures" / "dans 2 jours"
```

Formats français/anglais intégrés (mois, jours de semaine, temps relatif) ; devises courantes (EUR, USD, GBP, XOF, XAF, JPY, CNY, NGN, GHS) avec repli sur le code ISO pour les autres.
