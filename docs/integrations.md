# Intégrations

## Cartes

Trois fournisseurs dans `packages/maps/src/` (namespace `Engine\Maps\`) :

| Fournisseur | Confiance | Détail |
|---|---|---|
| **OpenStreetMap** (`OsmMap`) | Élevée, testé | Leaflet.js + tuiles OSM, aucune clé — vérifiée en conditions réelles sur device. |
| **Mapbox** (`MapboxMap`) | Élevée sur le principe, non testé | Mapbox GL JS v3, jeton d'accès public. Pas de compte disponible pour tester en réel. |
| **Google Maps** (`GoogleMap`) | Élevée sur le principe, non testé | Google Maps JavaScript API, clé restreinte par domaine/package. |

```php
Maps\MapView::make($lat, $lng, $zoom)   // choisit automatiquement Mapbox > Google Maps > OpenStreetMap selon .env
```

Rien configuré = OpenStreetMap, toujours disponible (voir `phpnitro.yml`'s `maps:`, `phpx maps`).

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

`packages/socialauth/src/` (namespace `Engine\SocialAuth\`) — **des services, pas des boutons pré-stylés**. Chaque fournisseur suit le même flux OAuth2 Authorization Code (base commune `OAuthProvider`) : `onClick()` redirige vers l'écran de connexion du fournisseur, ta propre route de callback appelle `exchangeCode()` qui fait l'échange serveur-à-serveur et te renvoie `{id, email, name}` — à toi de créer/connecter l'utilisateur avec.

```php
Button::make('Se connecter avec Google', onClick: GoogleSignIn::onClick($clientId, $redirectUri))

// Dans ta route de callback (reçoit ?code=...) :
$user = GoogleSignIn::exchangeCode($code, $clientId, $clientSecret, $redirectUri);
// $user === ['id' => '...', 'email' => '...', 'name' => '...'] ou null si échec
```

| Fournisseur | Particularité |
|---|---|
| `GoogleSignIn` | OAuth2 standard |
| `MicrosoftSignIn` | tenant `common` (comptes pro et perso) |
| `GithubSignIn` | OAuth2 standard |
| `SlackSignIn` | OpenID Connect (scopes `openid email profile`, pas les anciens scopes bot/workspace) |
| `FacebookSignIn` | OAuth2 standard |
| `XSignIn` | seul fournisseur nécessitant PKCE — le `code_verifier` est conservé en session entre `onClick()` et `exchangeCode()` |
| `AppleSignIn` | le seul sans `client_secret` statique : `AppleSignIn::clientSecret($teamId, $keyId, $clientId, $privateKeyPath)` génère le JWT ES256 requis (même idiome que `GoogleServiceAccount`, vérifié cryptographiquement avec une vraie paire de clés EC). Pas d'endpoint userinfo REST — les infos utilisateur n'arrivent que dans l'`id_token` du premier login. |

Tous **non vérifiés contre un vrai compte développeur** (aucun disponible dans l'environnement de développement) — même tier de confiance que Mapbox/Firebase. `AppleSignIn::normalize()` décode le JWT sans vérifier sa signature contre le JWKS d'Apple (acceptable seulement parce que le token vient directement de l'endpoint token d'Apple en TLS, jamais réutilisable tel quel pour un token reçu d'ailleurs).

## Formats (nombres, devises, dates)

`packages/format/src/Format.php` (namespace `Engine\Format\`) — équivalent `intl`, délibérément **sans dépendre de `ext-intl`** (présence non vérifiée dans le PHP cross-compilé pour Android) :

```php
Format::number(1234.5, 2);                 // "1 234,50"
Format::currency(1234.5, 'EUR');           // "1 234,50 €"
Format::date(new DateTimeImmutable(), 'd MMMM yyyy');  // "21 juillet 2026"
Format::relativeTime($date, locale: 'fr'); // "il y a 3 heures" / "dans 2 jours"
```

Formats français/anglais intégrés (mois, jours de semaine, temps relatif) ; devises courantes (EUR, USD, GBP, XOF, XAF, JPY, CNY, NGN, GHS) avec repli sur le code ISO pour les autres.
