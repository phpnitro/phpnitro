# Intégrations

## Cartes

`packages/maps/` (le widget HTML `Maps\MapView`/`OsmMap`/`MapboxMap`/`GoogleMap`) a été supprimé une fois remplacé par un vrai widget natif : `Engine\Native\MapView`, une véritable `org.osmdroid.views.MapView` overlayée au tap (tuiles OpenStreetMap réelles, pan/zoom natif, aucune clé API). Voir [docs/widgets.md](widgets.md).

## Firebase

`packages/firebase/src/` (namespace `Engine\Firebase\`) — aucune dépendance `kreait/firebase-php`/`google/apiclient` : uniquement du REST + `openssl_*`, même philosophie que `StripeCheckout`.

| Classe | Rôle | Authentification |
|---|---|---|
| `GoogleServiceAccount` | Jeton OAuth2 depuis un compte de service (JWT RS256 signé via `openssl_sign`) — brique partagée par les classes serveur ci-dessous. | Compte de service (JSON). |
| `FirebaseMessaging::send(...)` | Envoie une notification push (FCM HTTP v1). **Doit tourner sur ton propre serveur hébergé**, jamais depuis le PHP embarqué sur le téléphone. | Compte de service. |
| `FirebaseAuth::signIn(...)` / `::signUp(...)` | Connexion/inscription email + mot de passe via l'API REST Identity Toolkit. | Clé API web (client-safe). |
| `FirebaseAuth::signInWithGoogleIdToken(...)` | Échange un ID token Google (obtenu via Credential Manager côté Android) contre une session Firebase. | Clé API web. |
| `FirebaseAuth::signInWithFacebookAccessToken(...)` / `::signInWithGithubAccessToken(...)` | Idem pour Facebook/GitHub — prend l'`access_token` que `Engine\SocialAuth\FacebookSignIn`/`GithubSignIn::exchangeCode()` retourne déjà (voir section Authentification sociale ci-dessous). | Clé API web. |
| `FirebaseAuth::sendPasswordResetEmail(...)` | Déclenche l'email de réinitialisation envoyé par Firebase lui-même (aucun SMTP à configurer). | Clé API web. |
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

`packages/socialauth/src/` (namespace `Engine\SocialAuth\`) — Google est géré à part, via un vrai SDK natif (Credential Manager, voir `NativeDeviceBridge.kt::signInWithGoogle()`), pas ce package. Les quatre autres (Microsoft, GitHub, Facebook, Apple) n'ont pas d'équivalent SDK Android que ce framework embarque — un flux OAuth2 Authorization Code classique est donc l'approche native-appropriée pour eux, pas un compromis :

```php
use Engine\Device\UrlLauncher;
use Engine\SocialAuth\GithubSignIn;

// 1. Ouvre la page d'autorisation du fournisseur
UrlLauncher::openAction(GithubSignIn::authorizeUrl($clientId, $redirectUri));

// 2. Le fournisseur redirige vers phpnitro://oauth-callback?code=...
//    (App Link, voir NativeRenderPocActivity.onNewIntent()) — l'action
//    handler du développeur (public/index.php) récupère ensuite le
//    profil normalisé :
$profile = GithubSignIn::exchangeCode($code, $clientId, $clientSecret, $redirectUri);
// $profile = ['id' => ..., 'email' => ..., 'name' => ..., 'access_token' => ...]
```

`OAuthProvider` (classe de base abstraite) porte tout le flux partagé (`authorizeUrl()`/`exchangeCode()`) ; chaque fournisseur ne déclare que ses propres endpoints + la normalisation de son profil. Apple diffère sur un point réel : pas de `client_secret` statique, mais un JWT ES256 signé à la volée (`AppleSignIn::clientSecret()`) depuis la clé `.p8` téléchargée une fois sur Apple Developer.

`access_token` (le jeton brut du fournisseur, pas seulement le profil normalisé) est ce que `Engine\Firebase\FirebaseAuth::signInWithFacebookAccessToken()`/`::signInWithGithubAccessToken()` attendent pour fédérer la connexion avec Firebase — voir la section Firebase ci-dessus.

**Confiance** : `UNVERIFIED` dans le docblock de chaque fournisseur (Facebook/GitHub/Microsoft/Apple) — écrit d'après leur doc OAuth2 officielle respective, jamais testé contre une vraie app OAuth (aucun compte développeur disponible ici). Slack/X (Twitter), présents dans une génération antérieure de ce package (widget Tailwind, supprimé avec la bascule vers le rendu natif), n'ont pas encore de fournisseur `OAuthProvider` reconstruit.

## Formats (nombres, devises, dates)

`packages/format/src/Format.php` (namespace `Engine\Format\`) — équivalent `intl`, délibérément **sans dépendre de `ext-intl`** (présence non vérifiée dans le PHP cross-compilé pour Android) :

```php
Format::number(1234.5, 2);                 // "1 234,50"
Format::currency(1234.5, 'EUR');           // "1 234,50 €"
Format::date(new DateTimeImmutable(), 'd MMMM yyyy');  // "21 juillet 2026"
Format::relativeTime($date, locale: 'fr'); // "il y a 3 heures" / "dans 2 jours"
```

Formats français/anglais intégrés (mois, jours de semaine, temps relatif) ; devises courantes (EUR, USD, GBP, XOF, XAF, JPY, CNY, NGN, GHS) avec repli sur le code ISO pour les autres.
