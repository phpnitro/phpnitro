# PhpNitro (mobile framework)

Écris des interfaces mobiles modernes en PHP : des widgets (`Button`, `Text`, `Column`...) stylés avec Tailwind CSS, servis par un vrai runtime PHP, affichés dans une WebView native (Android WebView / WKWebView).

Contrairement à une première approche envisagée (transpiler le PHP vers Dart/Flutter), **PHP reste ici le vrai runtime** : ce n'est pas un langage source qui disparaît à la compilation, c'est le code qui s'exécute réellement à chaque interaction — comme le ferait un serveur web classique, mais embarqué sur le device. **Vérifié de bout en bout sur un vrai téléphone Android** (voir `android/README.md`).

**Exemple complet** : `examples/ecom` — une boutique en ligne (catalogue, panier, checkout, compte, carte, biométrie, suivi de commande en direct) qui utilise la quasi-totalité du framework. Voir `examples/ecom/README.md`.

## Architecture d'un projet

```
mon-app/
  composer.json  UN SEUL, à la racine — un seul vendor/ pour tout le projet
  public/        front controller (index.php, router.php, tailwind.css) — le point d'entrée HTTP
  lib/
    pages/     tes écrans (Screen) — vide au départ, tu les crées avec `phpx make:page` ;
               rien d'autre ici, ni code du framework ni front controller
    backend/   pure librairie "façon Symfony" (Controller / Entity / Repository / Service),
               pas de point d'entrée HTTP à elle seule — voir "Backend" plus bas
  packages/
    ui/src/         le widget SDK (Text, Button, Column...) — phpnitro/ui
    database/src/   connexion base de données — phpnitro/database
  android/   app Android (WebView native + PHP embarqué) — vérifiée sur device réel
  ios/       stub WKWebView — non testé (pas de Mac/Xcode disponible ici)
  assets/    images, polices, ET le JS du framework (assets/js/) — copiés automatiquement dans public/assets
  .env       config partagée par tout le projet (un seul fichier, à la racine)
  bin/       phpx (CLI)
```

Comme un projet Flutter (`android/`, `ios/`, `lib/`), sauf que `lib/` se scinde en `pages/` (présentation) et `backend/` (logique). Un seul `composer.json`/`vendor/` pour tout le projet : son autoload PSR-4 mappe directement `packages/ui/src`, `packages/database/src`, `lib/pages/app` et `lib/backend/src` — pas de path repository, pas de symlink Composer à gérer, juste des dossiers PSR-4. `lib/pages/` de ton projet ne contient jamais le code du framework ni le front controller, juste tes propres écrans, et démarre **vide** : tu crées tes pages toi-même (voir "CLI" plus bas).

`public/index.php` délègue toute route `/api/*` à `Backend\Kernel` **dans le même processus PHP** — pas un deuxième serveur à lancer, c'est implicite (voir "Backend" plus bas). C'est cette structure exacte que génère `phpx new`.

**Linux / macOS / Windows** : pas encore implémentés (ni même stubbés) — chaque plateforme desktop demanderait sa propre coquille native (GTK+WebKit, Cocoa+WKWebView, WebView2), un chantier à part entière. Pas commencé, contrairement à iOS qui a au moins un stub documenté.

Chaque widget est une classe PHP avec un constructeur (propriétés configurables, comme dans Flutter) et une méthode `render(): string` qui produit du HTML :

```php
Button::make('Connexion');
// -> <button class="bg-blue-600 ...">Connexion</button>
```

## Prérequis

- PHP ≥ 8.1 avec Composer
- Node.js + npm (uniquement pour reconstruire le CSS Tailwind si tu changes des classes utilitaires)
- Pour builder l'APK Android : Android SDK (compileSdk 35), Gradle ≥ 8.9, JDK 17 — **pas de Docker requis**, les binaires PHP pour le device sont déjà fournis dans le repo (`android/app/src/main/jniLibs/`)

## Démarrer un nouveau projet

```bash
php bin/phpx new mon-app
cd mon-app
composer install
bin/phpx make:page Home /
bin/phpx serve
```

Ouvre `http://127.0.0.1:8090/`. `phpx new` copie `lib/`, `packages/` (les packages du framework, en attendant Packagist — voir plus bas), `public/`, `android/` (binaires PHP inclus), `ios/`, `assets/`, `bin/`, `.env`, `composer.json` et `package.json` depuis ce dépôt vers l'emplacement de ton choix (comme `composer create-project`) — ton nouveau projet n'est pas imbriqué dans le framework. `lib/pages/app/` arrive **vide** : `make:page Home /` crée ta première page et la route racine.

## CLI (`bin/phpx`)

```bash
php bin/phpx serve                  # sert public/ sur le port 8090
php bin/phpx make:page About        # crée lib/pages/app/AboutPage.php + lib/backend/src/Controller/AboutController.php
                                     # enregistre /about (page) ET /api/about (controller)
php bin/phpx make:entity Product    # crée lib/backend/src/Entity/Product.php + Repository/ProductRepository.php
php bin/phpx new mon-app            # scaffold un nouveau projet complet (voir ci-dessus)
php bin/phpx bundle:android         # copie public/ + lib/ + packages/ + .env dans l'app Android
php bin/phpx payments                # liste les gateways déclarés dans phpnitro.yml et leur statut dans .env
php bin/phpx maps                    # idem pour les fournisseurs de carte (Mapbox/Google/OpenStreetMap)
php bin/phpx icon                    # régénère l'icône Android depuis phpnitro.yml's `icon` (sans refaire tout bundle:android)
php bin/phpx firebase                # idem pour le compte de service / project ID / clé API web Firebase
```

`make:page` génère la classe et l'ajoute automatiquement au routeur (`public/index.php`), **et** génère un Controller pairé dans `lib/backend/src/Controller/`, câblé dans `Backend\Kernel` sur la route `/api/...` correspondante — façon Symfony, sans attributs de routage : juste une entrée de plus dans le `match()` de `Kernel::handle()`. `make:entity` fait la même chose côté données : une classe Entity (propriétés simples, aucune logique de persistance) pairée à un Repository pré-câblé sur `phpnitro/database`. Les deux sont des points de départ — les champs/le schéma restent à ta charge, ça retire juste la paperasse.

Par défaut, `make:page Home` (sans second argument) enregistre la route `/home`, pas `/` — passe explicitement `/` en second argument pour la page racine.

### phpnitro.yml — le manifeste de l'app

```yaml
name: Mon App
description: ...
version: 1.0.0
php: ">=8.1"

icon: assets/icon.png       # optionnel — PNG carré, génère l'icône Android (mipmaps + icône adaptative)
icon_background: "#2563EB"  # optionnel — couleur de fond de l'icône adaptative (défaut blanc)

payments:
  kkiapay:
    public_key_env: KKIAPAY_PUBLIC_KEY
    secret_key_env: KKIAPAY_PRIVATE_KEY

maps:
  mapbox:
    access_token_env: MAPBOX_ACCESS_TOKEN
  google:
    api_key_env: GOOGLE_MAPS_API_KEY

firebase:
  service_account_env: FIREBASE_SERVICE_ACCOUNT_JSON
  project_id_env: FIREBASE_PROJECT_ID
  web_api_key_env: FIREBASE_WEB_API_KEY
```

Même rôle que `pubspec.yaml` pour Flutter : décrire l'app à un seul endroit plutôt que d'éparpiller ces infos. `name` est la source de vérité pour `APP_NAME` dans `.env` (`phpx serve`/`phpx bundle:android` le resynchronisent automatiquement) **et** pour le label natif Android (`strings.xml`'s `app_name`, resynchronisé par `phpx bundle:android`). `icon` (optionnel) génère l'icône du launcher Android — mipmaps legacy + icône adaptative — depuis un PNG carré, via `bin/generate-android-icon.php` (GD, aucune dépendance ImageMagick) ; sans cette clé, l'icône existante n'est pas touchée. `payments`/`maps`/`firebase` déclarent quelles variables d'environnement chaque gateway/fournisseur/config attend — sans jamais lire les clés elles-mêmes ; `phpx payments`/`phpx maps`/`phpx firebase` rapportent, pour chaque entrée déclarée, si elle est configurée, en mode démo (clé publique seule, pour les paiements) ou pas configurée du tout, directement depuis `.env`.

`phpnitro.yml` est copié tel quel par `phpx new`. `examples/ecom` a le sien aussi, mais en documentation seule : cet exemple n'utilise pas `bin/phpx`, donc rien n'y lit le fichier — `CheckoutPage` choisit son gateway directement depuis `.env` (voir [Paiement](#paiement)).

### Tester la CLI

```bash
bash bin/test.sh
```

Ce script fait un vrai `phpx new` dans un dossier temporaire, un vrai `composer install`, un vrai `make:page`/`make:entity`, lance un vrai serveur et l'interroge en HTTP (`/`, `/api/hello`, les routes générées), puis un vrai `bundle:android` — il vérifie le résultat réel de chaque commande, pas des mocks. Base-toi dessus pour valider un changement dans `bin/phpx`.

Un seul `vendor/bin/phpunit` (à la racine) couvre les deux suites de tests — widgets/Screen/Router/Csrf (`packages/ui/tests`) et la connexion base de données (`packages/database/tests`), voir `phpunit.xml`.

## Lancer le moteur de widgets

```bash
composer install
php -S 127.0.0.1:8090 -t public public/router.php   # ou: php bin/phpx serve
```

Tu dois voir l'écran `lib/pages/app/HomePage.php` rendu et stylé (démo de ce dépôt — un nouveau projet scaffoldé via `phpx new` démarre avec `lib/pages/app/` vide), avec un bouton "Incrémenter" qui augmente réellement un compteur côté serveur (état en session PHP), un lien "Réglages" (`/settings`), et un lien "Device" (`/device`) pour tester caméra/micro/localisation/vibreur.

`.env` (à la racine du projet, chargé via `symfony/dotenv`) contrôle par exemple `APP_NAME`, utilisé comme `<title>` de la page.

(`public/router.php` est nécessaire avec le serveur de dev PHP pour que les routes comme `/settings` soient bien résolues par `Engine\Router` tout en continuant à servir `tailwind.css` comme fichier statique.)

### Reconstruire le CSS après avoir changé des classes Tailwind

```bash
npm install   # une seule fois
npm run build
```

## Écrire un écran

Un écran est une classe qui étend `Screen` : elle déclare son état initial, ses actions (`onXxx`), et son `build()`.

```php
<?php

namespace Engine\App;

use Engine\Button;
use Engine\Column;
use Engine\Screen;
use Engine\Text;
use Engine\Widget;

final class HomePage extends Screen
{
    protected function initialState(): array
    {
        return ['count' => 0];
    }

    protected function onIncrement(): void
    {
        $this->state['count']++;
    }

    public function build(): Widget
    {
        return Column::make([
            Text::make('Compteur : ' . $this->state['count']),
            Button::make('Incrémenter', action: 'increment'),
        ]);
    }
}
```

Un clic sur `Button::make($label, action: 'increment')` soumet un POST au serveur PHP (`_action=increment`), qui appelle `onIncrement()`, sauvegarde le nouvel état en session, puis redirige (POST-redirect-GET, pas de resoumission au refresh).

## Navigation multi-écrans

Les routes sont déclarées dans `public/index.php` :

```php
$router = new Router([
    '/' => HomePage::class,
    '/settings' => SettingsPage::class,
    '/product/{id}' => ProductPage::class,
]);
```

Le widget `Link::make($label, $href)` génère un `<a href="...">` classique — une vraie route HTTP, résolue par `Engine\Router` (pas de table de routes côté client). Un chemin non déclaré renvoie une vraie 404, pas une erreur silencieuse.

### Paramètres entre pages

Une route comme `/product/{id}` capture le segment dans `$this->params['id']`, accessible dans `build()` (voir `lib/pages/app/ProductPage.php`). Chaque combinaison classe+paramètres a son propre état de session (deux visites de `/product/1` et `/product/2` ne partagent pas leur état).

### Pas de rechargement de page (`nav.js`)

Cliquer sur un `Link` ou soumettre un `Form`/`Button` ne recharge **pas** la page : `assets/js/nav.js` intercepte tout clic sur un lien et toute soumission de formulaire du même domaine, fait la requête avec un header (`X-Phpx-Partial: 1`), et remplace `<body>` par la réponse au lieu de naviguer — `history.pushState` tient l'URL à jour (et `popstate` recharge en arrière/avant). PHP reste l'unique source de rendu : le serveur renvoie exactement le même arbre de widgets qu'avant, juste encapsulé dans un petit JSON (`{html, path, theme}`) au lieu du document complet — `Engine\Navigation::isPartial()` détecte le header, `Engine\PageRenderer` choisit lequel des deux envoyer. Une action qui redirige vers un autre écran (connexion → accueil, checkout → confirmation) est résolue et rendue **côté serveur dans la même requête**, pas en deux allers-retours.

Sans JS (curl, navigateur avec JS désactivé, `bin/test.sh`) : `Navigation::isPartial()` est faux, et le comportement est exactement celui d'avant (POST → redirection 303 → GET, document complet à chaque fois) — rien ne casse, l'amélioration est strictement une amélioration progressive.

Deux pièges propres à ce genre de swap, déjà gérés :
- `innerHTML` n'exécute jamais les balises `<script>` qu'il contient — `nav.js` les recrée une par une (`document.createElement('script')`) après chaque swap, donc les widgets avec un script inline (boutons de paiement) continuent de fonctionner après une navigation, pas seulement au premier chargement.
- Tout ce qui s'attache au DOM une seule fois à `DOMContentLoaded` (gestes, polling `StreamBuilder`/`FutureBuilder`) doit aussi se réattacher après un swap — `nav.js` déclenche un événement `phpx:navigated` que `gestures.js`/`stream.js`/`future.js` écoutent pour se relier aux nouveaux éléments. `StreamBuilder` vérifie en plus que son élément est toujours attaché au DOM avant chaque requête de polling, pour qu'une navigation loin d'une page avec un live-tracker actif arrête vraiment le polling au lieu de le laisser tourner indéfiniment sur un élément détaché.

Sur Android, `history.pushState` alimente aussi la pile back/forward native de la WebView — `MainActivity.kt` intercepte le bouton retour matériel (`OnBackPressedCallback`) et appelle `webView.goBack()` tant qu'il reste des écrans dans cette pile, avant de laisser l'Activity se fermer normalement une fois arrivé au premier écran. Vérifié en conditions réelles sur téléphone (Infinix X6532) : navigation complète accueil → produit → panier → paiement (avec erreur de validation serveur puis biométrie) → confirmation de commande (fragment `StreamBuilder` en direct) → retour, sans aucun rechargement visible ni erreur JS.

Un widget qui a besoin de poster une action en JS (comme les boutons de paiement) utilise `window.phpxNav.submitAction(action, champs)` ou `window.phpxNav.submitForm(form, action, champs)` plutôt que de refaire son propre `fetch` — même mécanique de swap partout.

## Formulaires et actions paramétrées

`Form` regroupe des champs et poste une action nommée ; l'écran reçoit toutes les valeurs saisies dans `onXxx(array $data)`, et peut retourner un chemin pour rediriger :

```php
// build():
Form::make([
    TextField::make('username', label: 'Utilisateur'),
    TextField::make('password', label: 'Mot de passe', type: 'password'),
    Checkbox::make('remember', 'Se souvenir de moi'),
    Button::make('Se connecter'),
], action: 'login'),

// handler:
protected function onLogin(array $data): ?string
{
    if ($data['username'] === 'demo' && $data['password'] === 'demo') {
        return '/';              // redirection programmatique
    }
    $this->state['error'] = 'Identifiants invalides.';
    return null;                 // reste sur la page
}
```

Démo complète : `/login` (`lib/pages/app/LoginPage.php`), identifiants `demo`/`demo`.

### Afficher les erreurs explicitement

`ErrorBanner::make($this->state['error'])` — une boîte visuellement distincte (icône + fond/bordure rouges), pas une simple ligne de texte rouge ; retourne `''` si le message est `null`/vide, donc toujours safe à inclure sans condition. `TextField`/`Textarea`/`SelectBox` acceptent aussi un `error: string` optionnel (bordure rouge + message sous le champ, défaut `''` = rendu inchangé) pour une erreur au niveau du champ plutôt qu'un message global. Distinct de `Flash`/`FlashMessage` (message one-shot après redirection, consommé puis effacé) : une erreur de validation doit rester affichée à chaque nouvelle tentative tant qu'elle n'est pas corrigée, donc reste dans `$this->state`, pas dans `Flash`.

### Stepper (assistant multi-étapes)

`Stepper` reste stateless (en-tête de progression + le contenu de l'étape courante + boutons Retour/Suivant) — l'index d'étape et les données accumulées vivent dans `$this->state` de l'écran appelant, exactement comme `CheckoutPage` accumule ses erreurs de validation à travers plusieurs POST. Retour et Suivant doivent atteindre deux `onXxx()` différents à partir d'un seul `<form>` (que `Stepper` construit lui-même) : chaque bouton porte son propre `name="_action" value="..."` plutôt qu'un champ caché partagé — seul le bouton cliqué envoie sa paire, donc Retour préserve aussi les valeurs de l'étape en cours, pas seulement Suivant. Démo complète et fonctionnelle (pas juste un rendu statique) : `/widgets/stepper`.

Tous les POST (boutons, formulaires, gestes) embarquent un jeton CSRF vérifié globalement — requête forgée → 419.

## Widgets disponibles

Toutes les classes ci-dessous sont dans le namespace `Engine\` sauf préfixe explicite (`Maps\` = `Engine\Maps\`, `Dialogs\` = `Engine\Dialogs\`, `Payments\` = `Engine\Payments\`, `Firebase\` = `Engine\Firebase\`, `Device\` = `Engine\Device\` — des packages de service dédiés, voir `packages/maps`, `packages/dialogs`, `packages/payments`, `packages/firebase`, `packages/device`, sur le même modèle que `packages/database` : un second namespace PSR-4 dans le `composer.json` racine, pas un package Composer séparé).

**Widgets vs services.** La plupart des classes ci-dessous sont des widgets : `::make()` retourne un `Widget` qui rend son propre HTML complet. Mais un bouton d'action pré-stylé (paiement, vibreur, appareil photo...) impose sa propre apparence — si tu veux ton propre label/style, tu es coincé. `Engine\Device\` et une partie d'`Engine\Payments\` sont donc des **services** : des méthodes statiques qui retournent une expression JS brute à attacher à N'IMPORTE QUEL bouton via `Button::make($label, onClick: ...)`, plutôt qu'un widget imposé. Voir [Capacités du device](#capacités-du-device-caméra-micro-localisation-vibreur-biométrie-notifications-son-impression) et [Paiement](#paiement).

Chaque widget est démontré quelque part : la route `/widgets` de l'app démo racine (index + sous-pages par catégorie — layout, formulaires, média, cartes, dialogues) couvre tout sauf les paiements, exercés en conditions réelles dans [examples/ecom](examples/ecom/README.md#paiement) à la place.

| PHP | Rend en |
|---|---|
| `Text::make($content, $classes = '...')` | `<p>` |
| `Button::make($label, $action = null, $classes = '...', $onClick = null)` | `<button>` (ou `<form>` si `$action` est fourni) ; `$onClick` (expression JS brute, prioritaire sur `$action`) attache un service comme `Vibrate::onClick()` ou `Kkiapay::payOnClick()` sans passer par un widget dédié |
| `Column::make([$children], $classes = '...')` | `<div class="flex flex-col ...">` |
| `Row::make([$children], $classes = '...')` | `<div class="flex flex-row ...">` |
| `Container::make($child, $classes = '...')` | `<div>` à un seul enfant |
| `Image::make($src, $alt = '', $classes = '...')` | `<img>` |
| `Link::make($label, $href, $classes = '...')` | `<a href="...">` |
| `ListView::make([$children], $classes = '...')` | liste verticale avec séparateurs |
| `SingleScrollView::make($child, $classes = '...')` | conteneur défilable (vertical, style `overflow-y-auto`) |
| `BottomNavigation::make([['label'=>..,'href'=>..], ...])` | `<nav>` fixée en bas d'écran — rendue **une seule fois** par `PageRenderer` (voir `Screen::showsBottomNav()`, `Scaffold`'s `hasBottomNav`), jamais recréée par `nav.js` lors d'une navigation |
| `FloatingActionButton::make($label, $action = null, $classes = '...')` | bouton rond flottant (bottom-right) |
| `GestureDetector::make($child, onDoubleClick:, onSwipeLeft:, onSwipeRight:)` | `<div>` détectant double-clic / swipe (déclenche une action serveur) |
| `ThemeToggle::make()` | bouton bascule clair/sombre |
| `LocationButton::make($label)` | déclenche `navigator.geolocation`, affiche les coordonnées |
| `Device\Vibrate`, `Device\Notify`, `Device\Sound`, `Device\Printer`, `Device\Microphone`, `Device\Fingerprint`, `Device\Camera`, `Device\ImagePicker` | services (JS attachable via `Button::make(..., onClick: ...)`), pas des widgets — voir [Capacités du device](#capacités-du-device-caméra-micro-localisation-vibreur-biométrie-notifications-son-impression) |
| `Maps\MapView::make($lat, $lng, $zoom = 15)` | résout automatiquement Mapbox &gt; Google Maps &gt; OpenStreetMap selon `.env` — voir [Cartes](#cartes) |
| `Maps\OsmMap::make($lat, $lng, $zoom = 15)` | carte OpenStreetMap interactive (Leaflet, zéro clé API) |
| `Maps\MapboxMap::make($accessToken, $lat, $lng, $zoom = 15)` | carte Mapbox GL JS interactive (jeton client, pas de clé secrète) |
| `Maps\GoogleMap::make($apiKey, $lat, $lng, $zoom = 15)` | carte Google Maps JavaScript API interactive |
| `ProgressBar::make($value)` | barre de progression linéaire (0-100, calculée côté serveur) |
| `CircularProgress::make($value, $size = 64)` | indicateur circulaire (SVG pur, pas de JS/canvas) |
| `Drawer::make([$items])` + `DrawerToggle::make()` | menu latéral coulissant, zéro JS (case à cocher cachée + variantes Tailwind `peer-checked:`) |
| `Dropdown::make($label, [$items])` | menu déroulant, zéro JS (`<details>`/`<summary>` natif HTML) |
| `Flash::set($message)` + `FlashMessage::make()` | message flash en session, auto-masqué (animation CSS, pas de JS) |
| `StreamBuilder::make($endpoint, $render)` | interroge une route JSON en polling et re-rend le widget à chaque changement |
| `FutureBuilder::make($endpoint, $loading)` | charge une route **une seule fois** au chargement de page (pas de polling), affiche `$loading` en attendant |
| `Center::make($child)` | centre l'enfant (`flex items-center justify-center`) |
| `Align::make($child, $alignment)` | aligne l'enfant selon une constante `Alignment::*` |
| `Padding::make($child, $classes = 'p-4')` | ajoute un espacement interne (classes Tailwind `p-*`) |
| `Margin::make($child, $classes = 'm-4')` | ajoute un espacement externe (classes Tailwind `m-*`) |
| `Divider::make($classes = '...')` | ligne de séparation (`<hr>`) |
| `IconButton::make($icon, $action = null, $classes = '...', $ariaLabel = '', $onClick = null)` | bouton icône seule (voir `Icon::*` pour le jeu d'icônes), même comportement d'action/`onClick` que `Button` |
| `Html::raw($html)` | passthrough HTML/JS brut — l'échappatoire qu'utilisent les services (`Engine\Device\`, `Engine\Payments\`) pour composer script tags et éléments de sortie dans un arbre de widgets |
| `Textarea::make($name, $label = '', $value = '', $placeholder = '', $rows = 4, $error = '')` | `<textarea>` |
| `DatePicker::make($name, $label = '', $value = '', $min = '', $max = '')` | `<input type="date">` — le sélecteur natif Android s'affiche via le WebView, zéro JS |
| `TimePicker::make($name, $label = '', $value = '')` | `<input type="time">` — même chose côté sélecteur natif |
| `AudioPlayer::make($src, $controls = true, $autoplay = false, $loop = false)` | `<audio>` avec contrôles natifs Chromium |
| `VideoPlayer::make($src, $controls = true, $autoplay = false, $loop = false, $poster = '')` | `<video>` avec contrôles natifs Chromium |
| `Image::network($url, $alt = '', $classes = '...')` | alias de `Image::make` (parité nommage Flutter `Image.network`/`NetworkImage`) |
| `PageView::make([$pages])` | carrousel de pages avec swipe natif (CSS `scroll-snap`, zéro JS) |
| `Table::make($rows, $headers = [], $border = TableBorder::ALL)` | `<table>` ; `$rows` accepte des chaînes ou des `Widget` par cellule |
| `TableBorder::ALL\|HORIZONTAL\|VERTICAL\|NONE` | préréglages de bordures (`divide-x`/`divide-y` Tailwind), équivalent DOM du `TableBorder` Flutter |
| `Navigator::to($path)` / `Navigator::back($fallback = '/')` / `Navigator::link($label, $path)` | sucre de nommage façon Flutter au-dessus de vraies URLs/redirections HTTP (voir `Screen::handle()`) |
| `GoogleTranslate::make($pageLanguage = 'fr', $includedLanguages = '...')` | widget officiel Google Website Translator (traduction client-side, nécessite le réseau) |
| `Translator::load($locale, $translations)` / `::t($key, $params = [])` | i18n par clés, côté serveur, pour les textes que tu maîtrises (indépendant de `GoogleTranslate`) |
| `SwitchToggle::make($name, $label, $on = false)` | interrupteur on/off (case à cocher cachée + variantes `peer-checked:`, zéro JS) |
| `Stepper::make($currentStep, $totalSteps, $stepLabels, $body, $backAction = null, $nextAction = null)` | assistant multi-étapes — voir [Stepper](#stepper-assistant-multi-étapes) |
| `ErrorBanner::make($message)` | boîte d'erreur explicite (icône + fond rouge) — voir [Afficher les erreurs explicitement](#afficher-les-erreurs-explicitement) |
| `Dialogs\AlertButton::make($message, $label = '...', $title = '')` | boîte de dialogue **native** Android (`AlertDialog`), repli `window.alert()` — voir [Boîtes de dialogue](#boîtes-de-dialogue) |
| `Dialogs\ConfirmButton::make($message, $action, $label = '...', $title = '')` | confirmation native ; n'appelle `$action` que si l'utilisateur confirme — voir [Boîtes de dialogue](#boîtes-de-dialogue) |
| `Payments\Kkiapay::scriptTag()` + `::payOnClick($publicKey, $amount, $sandbox = true)` + `::onSuccess($action)` | service paiement Kkiapay, à attacher via `Button::make(..., onClick: ...)` — voir [Paiement](#paiement) |
| `Payments\PaypalButton::make($clientId, $amount, $action, $currency = 'EUR')` | paiement PayPal (vrai JS SDK, `paypal.Buttons()`) — reste un widget (exception documentée) — voir [Paiement](#paiement) |
| `Payments\Fedapay::scriptTag()` + `::payOnClick($publicKey, $amount, $action, $description = '', $sandbox = true)` | service paiement FedaPay — voir [Paiement](#paiement) |
| `Button::make($label, action: $action)` + `Payments\StripeCheckout::createSessionUrl(...)` | paiement Stripe (Checkout hébergé, aucun SDK client, aucun widget dédié) — voir [Paiement](#paiement) |
| `Payments\Stripe::cardElement($publicKey, $clientSecret)` + `::confirmPaymentOnClick($action)` | champ carte intégré (Stripe Elements, iframe géré par Stripe) + déclencheur de confirmation décorrélé — voir [Paiement](#paiement) |
| `Payments\Feexpay::scriptTag()` + `::payOnClick($shopId, $amount, $action, $sandbox = true)` | service paiement Feexpay — gabarit non vérifié, voir [Paiement](#paiement) |
| `Payments\IziChangePay::scriptTag()` + `::payOnClick($apiKey, $amount, $action, $sandbox = true)` | service paiement iZiChangePay — gabarit non vérifié, voir [Paiement](#paiement) |
| `Payments\TresorPay::scriptTag()` + `::payOnClick($apiKey, $amount, $action, $sandbox = true)` | service paiement TresorPay — gabarit non vérifié, voir [Paiement](#paiement) |
| `Firebase\FirebaseAuth::signIn($webApiKey, $email, $password)` / `::signUp(...)` | connexion/inscription via l'API REST Identity Toolkit — voir [Firebase](#firebase) |
| `Firebase\FirebaseMessaging::send($serviceAccountPath, $projectId, $token, $title, $body)` | envoi d'une notification push (FCM HTTP v1) — voir [Firebase](#firebase) |
| `Firebase\Firestore::get(...)` / `::set(...)` | client REST minimal Firestore (un document à la fois) — voir [Firebase](#firebase) |
| `Firebase\GoogleServiceAccount::accessToken($serviceAccountPath, $scope)` | jeton OAuth2 de compte de service, partagé par FCM et Firestore — voir [Firebase](#firebase) |

Le paramètre `$classes` accepte n'importe quelle classe Tailwind — valeur par défaut sensée, entièrement remplaçable, comme un widget Flutter personnalisable.

`Alignment::TOP_LEFT\|TOP_CENTER\|TOP_RIGHT\|CENTER_LEFT\|CENTER\|CENTER_RIGHT\|BOTTOM_LEFT\|BOTTOM_CENTER\|BOTTOM_RIGHT` couvre le même rôle que `Alignment`/`AxisAlignment` côté Flutter : des préréglages `items-*`/`justify-*` Tailwind, pas un objet géométrique séparé.

## API de style typée (façon Flutter)

En plus de `$classes` (chaîne Tailwind libre), `Text` accepte des paramètres typés qui priment sur `$classes` dès qu'au moins un est fourni :

```php
Text::make('Titre', size: TextSize::XL2, weight: FontWeight::BOLD, color: Color::gray(900));
```

`TextSize` et `FontWeight` sont des enums (`TextSize::SM|BASE|LG|XL|XL2|XL3`, `FontWeight::NORMAL|MEDIUM|SEMIBOLD|BOLD`), `Color::gray/blue/red/green(shade)` ou `Color::of('nom', shade)` pour n'importe quelle couleur Tailwind. Ce premier jet ne couvre que `Text` — même principe applicable aux autres widgets par la suite.

## Concepts internes Flutter sans équivalent DOM

Flutter a son propre moteur de rendu (Skia), donc certains de ses types sont des réglages internes à ce moteur — pas des widgets. Comme PhpNitro rend vers du DOM/CSS (pas de canvas custom), ces concepts n'ont pas de classe dédiée : ils se traduisent directement en classes Tailwind, déjà utilisables partout où `$classes` est accepté.

| Flutter | Équivalent PhpNitro |
|---|---|
| `BoxFit` (`cover`, `contain`, `fill`...) | classes Tailwind `object-cover`, `object-contain`, `object-fill` sur `Image::make(..., classes: 'object-cover w-full h-40')` |
| `BoxShape` (`circle`, `rectangle`) | `rounded-full` vs `rounded-none`/`rounded-lg` sur `Container`/`Image` |
| `Brightness` (`light`, `dark`) | déjà couvert nativement, voir [Mode clair/sombre](#mode-clairsombre) — pas besoin d'un type séparé |
| `Clip` (`hardEdge`, `antiAlias`...) | `overflow-hidden` (+ `rounded-*` pour les coins) sur le conteneur |
| `AxisAlignment` (`MainAxisAlignment`/`CrossAxisAlignment`) | classes flex Tailwind (`justify-*`, `items-*`) directement sur `Column`/`Row`, ou les préréglages `Alignment::*` pour `Align`/`Center` |
| `ButtonBarLayoutBehavior` | `justify-between`/`justify-end` + `gap-*` sur un `Row` contenant les boutons |
| `ButtonTextTheme` | `$classes` du `Button` lui-même (couleur/poids de texte Tailwind) |
| `ChangeReportingBehavior` | sans objet ici : chaque action serveur (`onXxx()`) rend un nouvel état complet, il n'y a pas de diff à signaler séparément |

Autrement dit : si un concept Flutter décrit *comment le moteur de rendu dessine*, il devient une classe Tailwind ; s'il décrit *un élément affiché à l'écran*, il devient un widget PHP.

## Paiement

Sept gateways dans `packages/payments/src/` (namespace `Engine\Payments\`) et une intégration complète et testée dans `examples/ecom` (`CheckoutPage`). Cinq d'entre eux (Kkiapay, FedaPay, Feexpay, iZiChangePay, TresorPay) sont des **services**, pas des widgets : chaque classe expose `scriptTag(): Widget` (le `<script>` du SDK, à placer une fois sur la page), `payOnClick(...): string` (le déclencheur, attachable via `Button::make($label, onClick: ...)` à n'importe quel bouton) et, pour Kkiapay seulement, `onSuccess(string $action): Widget` (Kkiapay enregistre son callback de succès globalement via `addSuccessListener`, séparément du clic qui ouvre le widget — les quatre autres passent leur callback directement dans l'appel d'init du SDK). PayPal et Stripe Elements restent des widgets, pour des raisons documentées ci-dessous plutôt que par oubli.

Toutes suivent la même règle : un événement de succès côté client (`addSuccessListener`, `onApprove`...) **n'est jamais une preuve de paiement**, juste un signal d'UI — la commande n'est créée qu'après une vérification serveur-à-serveur avec la clé **privée/secrète**. La confiance dans chaque implémentation varie beaucoup d'un gateway à l'autre :

| Gateway | Confiance | Ce qui est vérifié |
|---|---|---|
| **Kkiapay** (`Payments\Kkiapay`) | Élevée | Pattern d'origine — script SDK + `transaction_id` + endpoint de vérification documenté. Non testé contre un vrai compte sandbox. |
| **PayPal** (`Payments\PaypalButton`, widget) | Élevée | Vrai JS SDK (`paypal.Buttons()`), flux OAuth2 + capture server-side standard et bien documenté. Non testé contre une vraie app sandbox. Reste un widget : le SDK PayPal dessine lui-même son bouton dans un conteneur qu'il contrôle entièrement (contrainte de marque/conformité PayPal) — il n'y a pas d'`onclick` à extraire pour l'attacher à un bouton externe. |
| **FedaPay** (`Payments\Fedapay`) | Moyenne-élevée | Même forme que Kkiapay (`FedaPay.init()`). Non testé. |
| **Stripe** (redirection, `Button::make` + `Payments\StripeCheckout`) | Élevée sur le principe, non testé sur l'appel réel | Checkout hébergé (`StripeCheckout::createSessionUrl()`, API REST directe, aucun SDK client nécessaire) — un simple `Button::make($label, action: $action)` suffit, pas de widget dédié (l'ancien `StripeButton` a été supprimé, il n'ajoutait rien). Confirmé qu'une clé invalide échoue proprement (pas de crash), l'appel réel à l'API Stripe n'a pas pu être testé (pas de clé sandbox disponible ici). |
| **Stripe Elements** (`Payments\Stripe::cardElement()` + `::confirmPaymentOnClick()`) | Élevée sur le principe, non testé sur l'appel réel | Champ carte intégré via Stripe Elements (`stripe.confirmCardPayment`), pas un formulaire `TextField` brut — la carte reste dans un iframe géré par Stripe, jamais dans notre DOM/serveur (voir la note PCI-DSS ci-dessous). `cardElement()` reste un point de montage (widget), mais le bouton de confirmation est décorrélé via `confirmPaymentOnClick(): string`, attachable à n'importe quel bouton. Activé automatiquement quand `STRIPE_PUBLIC_KEY` **et** `STRIPE_SECRET_KEY` sont renseignés (secrète seule = redirection hébergée à la place). |
| **Feexpay, iZiChangePay, TresorPay** (`Payments\Feexpay`, `Payments\IziChangePay`, `Payments\TresorPay`) | Faible à très faible | Gabarits structurels seulement (URL de script et nom des fonctions JS marqués `TODO`, à vérifier contre la doc de chaque gateway) — `CheckoutPage` refuse la transaction dès qu'une clé secrète est configurée plutôt que de faire semblant de la vérifier avec un appel non confirmé. |

`examples/ecom/.env` documente les variables de chaque gateway ; `/checkout` choisit le **premier** gateway configuré dans cet ordre (voir `CheckoutPage::selectPaymentWidgets()`) — rien de configuré = mode démo (comportement d'avant, commande créée directement sans paiement). Le déclencheur stashe le formulaire englobant dans une variable JS partagée (`window.__phpxPaymentForm`) au moment du clic, donc si le bouton est placé à l'intérieur d'un `Form::make(...)`, ses autres champs (nom, adresse...) sont sérialisés et postés avec l'identifiant de transaction — utile pour un vrai checkout qui a besoin des infos de livraison en plus du paiement.

Exemple (remplace l'ancien `KkiapayButton::make($key, $amount, action: 'confirmKkiapay')`) :
```php
Column::make([
    Kkiapay::scriptTag(),
    Kkiapay::onSuccess(action: 'confirmKkiapay'),
    Button::make('Payer avec mon bouton perso', onClick: Kkiapay::payOnClick($key, $amount)),
])
```

**Pourquoi pas un simple formulaire carte bancaire ?** Aucune intégration ici ne laisse jamais une donnée de carte brute atteindre notre propre DOM/serveur — que ce soit par redirection hébergée ou par un widget JS fournisseur. Un widget construit avec des `TextField` classiques pour numéro/CVV/expiration, postés via `Form`/`$data` vers notre propre serveur, mettrait cette donnée en **scope PCI-DSS SAQ D** (audit complet, segmentation réseau...) — une vraie régression de sécurité. `Stripe::cardElement()` suit donc le même principe que les autres gateways : la partie sensible reste hors de notre contrôle (iframe Stripe), seul un identifiant déjà confirmé (`payment_intent_id`) transite par notre serveur, revérifié via `StripeCheckout::retrievePaymentIntent()` avant de créer la commande.

Pour ajouter un autre gateway : `packages/payments/src/Kkiapay.php` ou `Fedapay.php` servent de modèle pour un service à SDK JS classique ; `StripeCheckout.php` pour un flux de redirection hébergé sans SDK client.

## Cartes

Trois fournisseurs dans `packages/maps/src/` (namespace `Engine\Maps\`) :

| Fournisseur | Confiance | Détail |
|---|---|---|
| **OpenStreetMap** (`OsmMap`) | Élevée, testé | Leaflet.js + tuiles `tile.openstreetmap.org`, aucune clé — carte interactive réelle (pan/zoom/marqueur), vérifiée en conditions réelles sur device (`DevicePage`, `examples/ecom/ProductPage`). |
| **Mapbox** (`MapboxMap`) | Élevée sur le principe, non testé | Mapbox GL JS v3, jeton d'accès public (client-safe par conception chez Mapbox). Implémenté d'après la doc officielle, pas de compte Mapbox disponible ici pour tester en réel. |
| **Google Maps** (`GoogleMap`) | Élevée sur le principe, non testé | Google Maps JavaScript API, clé restreinte par domaine/package (façon officielle de l'utiliser côté client). Implémenté d'après la doc officielle, pas de projet Google Cloud disponible ici pour tester en réel. |

`Maps\MapView::make($lat, $lng, $zoom)` choisit automatiquement Mapbox > Google Maps > OpenStreetMap selon `MAPBOX_ACCESS_TOKEN`/`GOOGLE_MAPS_API_KEY` dans `.env` — même idiome de priorité que `CheckoutPage::selectPaymentWidgets()` (voir `phpnitro.yml`'s `maps:`, `phpx maps`). Rien configuré = OpenStreetMap, toujours disponible.

## Boîtes de dialogue

`packages/dialogs/src/` (namespace `Engine\Dialogs\`) — `AlertButton`/`ConfirmButton`, natives d'abord : `assets/js/dialogs.js` (`window.phpxDialogs`) appelle une vraie `AlertDialog` Android via `WebAppInterface.showAlertDialog()`/`showConfirmDialog()` (même idiome de callback que `showBiometricPrompt()`), et ne retombe sur `window.alert()`/`window.confirm()` du navigateur que si le pont natif est absent. `ConfirmButton` n'appelle l'action serveur (`phpxNav.submitAction()`) que depuis le callback de confirmation — annuler la boîte de dialogue ne touche jamais le serveur.

## Firebase

`packages/firebase/src/` (namespace `Engine\Firebase\`) — aucune dépendance `kreait/firebase-php`/`google/apiclient` : uniquement du REST + les fonctions `openssl_*` de PHP, même philosophie que `StripeCheckout`.

| Classe | Rôle | Authentification |
|---|---|---|
| `GoogleServiceAccount` | Jeton OAuth2 à partir d'un compte de service (flow "JWT Bearer" standard de Google : JWT signé RS256 via `openssl_sign`, échangé contre un access token) — brique partagée par les deux classes suivantes. | Compte de service (fichier JSON téléchargé depuis la console Firebase). |
| `FirebaseMessaging::send(...)` | Envoie une notification push (FCM HTTP v1). Finit la partie qui manquait : le stockage des tokens (`fcm_tokens`, `/api/fcm/register`) était déjà réel et testé ; il manquait l'envoi. **Doit tourner sur ton propre serveur hébergé, jamais depuis le PHP embarqué sur le téléphone** — un compte de service est un vrai secret serveur, qui ne doit jamais finir dans un APK. | Compte de service. |
| `FirebaseAuth::signIn(...)` / `::signUp(...)` | Connecte/inscrit un utilisateur final via l'API REST Identity Toolkit — remplace utile de la session + `UserRepository` existante, pas un remplacement forcé (les deux coexistent). | Clé API web (client-safe par conception Firebase, distincte du compte de service). |
| `Firestore::get(...)` / `::set(...)` | Client REST minimal (un document à la fois) — une alternative à `Database::connection()` (Doctrine DBAL/SQL), pas branchée dedans : Firestore n'est pas un driver DBAL. | Compte de service. |

Confiance : même tier que Mapbox/Google Maps — implémenté d'après la doc officielle Google/Firebase (flows stables et bien documentés), mais rien n'a pu être testé contre un vrai projet Firebase (aucun compte de service ni clé disponible dans cet environnement). Le JWT signing a un test dédié (`packages/firebase/tests/GoogleServiceAccountTest.php`) qui vérifie la structure et la signature RS256 avec une paire de clés générée à la volée, sans réseau.

`phpnitro.yml`'s `firebase:` déclare les 3 noms de variables d'environnement attendues (`phpx firebase` rapporte leur statut) ; démo `FirebaseAuth` sur `/widgets/firebase-auth` (affiche un message explicite si `FIREBASE_WEB_API_KEY` n'est pas configuré, plutôt que de tenter un appel réseau voué à l'échec).

## Mode clair/sombre

`ThemeToggle::make()` bascule le thème via une vraie requête POST (`_action=toggleTheme`, intercepté globalement dans `index.php`, stocké en session, appliqué comme classe `dark` sur `<html>`). Les widgets utilisent les variantes `dark:` de Tailwind (`text-gray-900 dark:text-gray-100`, etc.).

## Gestes (double-clic, swipe)

`GestureDetector` est le seul endroit du framework qui utilise du JavaScript côté client (`assets/js/gestures.js`) — nécessaire car un double-clic ou un swipe ne peut pas être détecté en HTTP pur. Le JS ne fait que déclencher la même mécanique d'action que les boutons (`fetch` POST `_action=...`), donc le serveur ne voit aucune différence entre un clic de bouton et un geste détecté.

## Capacités du device (caméra, micro, localisation, vibreur, biométrie, notifications, son, impression)

`Engine\Device\` (`packages/device/src/`) — des **services**, pas des widgets : chaque classe expose une méthode statique qui retourne soit une expression JS à attacher via `Button::make($label, onClick: ...)` à N'IMPORTE QUEL bouton (le tien, avec ton propre label/style), soit un élément de sortie (`Widget`, via `Html::raw`) à placer où tu veux. Rien n'impose plus un bouton pré-stylé — voir `lib/pages/app/DevicePage.php` (route `/device`) pour un exemple complet de composition.

| Service | Méthode(s) |
|---|---|
| `Device\Vibrate` | `::onClick($milliseconds = 200): string` |
| `Device\Notify` | `::onClick($title, $message): string` |
| `Device\Sound` | `::onClick($url): string` |
| `Device\Printer` | `::onClick(): string` (`Print` est un mot réservé PHP) |
| `Device\Microphone` | `::onClick($outputId): string` + `::outputElement($id): Widget` |
| `Device\Fingerprint` | `::onClick($outputId): string` + `::outputElement($id): Widget` |
| `Device\Camera` | `::openOnClick($videoId): string` + `::captureOnClick($imageId): string` + `::videoElement($id): Widget` + `::imageElement($id): Widget` |
| `Device\ImagePicker` | `::pickOnClick($previewId, $hiddenFieldId): string` + `::hiddenField($name, $id): Widget` + `::previewElement($id): Widget` |

Exemple (remplace l'ancien `VibrateButton::make()`) :
```php
Button::make('Faire vibrer mon bouton perso', onClick: Vibrate::onClick(300), classes: 'bg-purple-600 text-white ...')
```

`LocationButton` (dans `packages/ui/src/`, namespace `Engine\`) reste un widget classique — non concerné par cette conversion.

Tous ces services passent par `assets/js/device.js` (`window.phpxDevice`), qui **préfère toujours le pont natif** (`window.AndroidNative`, exposé par `android/.../WebAppInterface.kt`) et ne retombe sur les Web APIs standard que si ce pont est absent (navigateur, tests locaux).

**Ce qui passe par du vrai code natif Kotlin (pas une Web API médiée par la WebView) :**
- **Vibreur** — `Vibrator` directement.
- **Caméra** — lance la vraie app Camera du système (`ActivityResultContracts.TakePicturePreview`).
- **Biométrie** — `BiometricPrompt` (androidx.biometric). **Important** : WebView n'implémente PAS WebAuthn/FIDO2 comme le fait Chrome-l'application — `navigator.credentials` y est absent ou non fonctionnel même avec une empreinte enregistrée. Le pont natif est donc la seule voie fiable ici, pas une optimisation.
- **Notifications** — `NotificationCompat`, fonctionne **hors-ligne**, indépendant de tout service push.
- **Son** — `MediaPlayer`, continue de jouer correctement à travers le verrouillage écran (contrairement à une balise `<audio>` de WebView).
- **Impression / PDF** — le vrai pipeline d'impression Android (`WebView.createPrintDocumentAdapter` + `PrintManager`, le flux système "Enregistrer en PDF") — pas de bibliothèque PDF PHP.
- **Sélecteur d'image** — la vraie app galerie/fichiers du système (`ActivityResultContracts.GetContent`).

**Ce qui reste médié par la WebView (mais reste réel, pas simulé)** : géolocation et micro délèguent déjà aux vraies APIs Android en coulisses (`FusedLocationProvider`, pipeline audio natif) via Chromium — fonctionnel, juste pas un pont Kotlin dédié pour l'instant.

Ce que ça ne donne *pas* encore : un flux caméra live entièrement natif avec contrôles avancés (CameraX/Camera2 complet, ISO manuel...) — seulement la capture photo via l'app Camera native.

## Backend (API)

`lib/backend/` est une **pure librairie** — pas de `public/`, pas de point d'entrée HTTP à lui tout seul. Le seul front controller du projet, `public/index.php`, délègue toute route `/api/*` à `Backend\Kernel::handle()`, en mémoire (pas de requête réseau, pas de second serveur à lancer). Zéro configuration supplémentaire, y compris dans l'app Android : le backend est toujours disponible, implicitement.

```bash
curl http://127.0.0.1:8090/api/hello
curl http://127.0.0.1:8090/api/health
curl http://127.0.0.1:8090/api/visits   # incrémente et retourne un compteur stocké en base réelle
```

### Génération de code (façon Symfony)

`phpx make:page About` crée `lib/pages/app/AboutPage.php` **et** `lib/backend/src/Controller/AboutController.php`, câblés respectivement sur `/about` et `/api/about`. `phpx make:entity Product` crée `lib/backend/src/Entity/Product.php` et `lib/backend/src/Repository/ProductRepository.php`, ce dernier pré-câblé sur `phpnitro/database`. Voir [CLI](#cli-binphpx) plus haut.

Structure :
```
lib/backend/
  src/
    Kernel.php             dispatch route -> Controller (le seul point d'entrée, réutilisé par public/index.php)
    Controller/            logique de requête -> réponse (HelloController.php, générés par make:page)
    Entity/                 objets métier, propriétés simples (générés par make:entity)
    Repository/             accès aux données (générés par make:entity, ou FcmTokenRepository/VisitRepository)
    Service/                logique métier réutilisable entre plusieurs contrôleurs
```

### Upload d'images

`POST /api/upload` avec `{"image": "data:image/png;base64,..."}` (exactement ce que produit le widget `ImagePicker`) décode et stocke le fichier dans `lib/backend/var/uploads/`, et `GET /api/uploads/{filename}` le ressert avec le bon `Content-Type` (déterminé par extension, sans dépendre de `ext-fileinfo` — pas garanti présent dans le PHP cross-compilé pour Android). Voir `Backend\Service\ImageUploadService`.

### Base de données

`Engine\Database\Database::connection()` (Doctrine DBAL) — un dossier PSR-4 séparé (`packages/database/src/`, mappé dans le `composer.json` racine sous `Engine\Database\`, même principe que `phpnitro/ui`). SQLite par défaut (`lib/backend/var/data.sqlite`, créé automatiquement au premier appel, zéro config), ou MySQL/PostgreSQL en définissant `DATABASE_URL` dans `.env` (voir les exemples commentés dans ce fichier). Même code, seul le DSN change. `VisitRepository` (`lib/backend/src/Repository/VisitRepository.php`) démontre un vrai cycle create-table/insert/count. `libsqlite3.so` est embarqué avec le binaire PHP dans l'app Android (voir plus bas).

`packages/database` ne connaît pas la structure de dossiers de l'app qui le consomme, donc il ne devine pas de chemin par défaut : `public/index.php` épingle `lib/backend/var/data.sqlite` une fois au démarrage via `Database::useSqlitePath()`, avant toute route — un seul autoloader pour tout le projet, donc un seul endroit où faire cette épingle plutôt qu'un par point d'entrée.

La connexion réessaie automatiquement (3 tentatives, avec backoff) à l'établissement initial, et se reconnecte silencieusement si une connexion déjà ouverte a été coupée en cours de session (utile avec un `DATABASE_URL` distant) — un appelant récupère toujours une connexion utilisable plutôt qu'une exception PDO brute.

## Construire l'APK (PHP embarqué sur le device)

L'app Android embarque un **vrai PHP 8.4 cross-compilé pour Android** (via le NDK, deux architectures : `armeabi-v7a` et `arm64-v8a`, déjà présentes dans `android/app/src/main/jniLibs/` — **aucun Docker ni compilation requise** pour builder l'app). Au lancement, `PhpServer.kt` copie l'app PHP des assets vers `filesDir`, démarre le binaire embarqué sur un **port choisi dynamiquement** (évite tout conflit si plusieurs apps construites avec ce framework tournent sur le même device), et la WebView s'y connecte : **PHP tourne réellement sur le téléphone**, pas sur un serveur distant. **Vérifié de bout en bout** sur un Infinix X6532 (Android 14, `armeabi-v7a`) — y compris l'authentification biométrique native.

Un vrai **splash screen natif** (Android 12+ SplashScreen API) reste affiché exactement jusqu'à ce que le serveur PHP ait démarré et que la page ait fini de charger — pas de délai fixe deviné, pas de flash d'écran blanc pendant le démarrage. Icône d'app adaptative incluse (vectorielle, éclair blanc sur dégradé orange/rouge — voir `branding/`).

```bash
php bin/phpx bundle:android   # copie public/ + lib/ + packages/ + composer.json (vendor --no-dev) + .env (APP_DEBUG=false) dans les assets
cd android
gradle :app:assembleDebug     # ou via Android Studio
# → android/app/build/outputs/apk/debug/app-debug.apk
```

Prérequis build : Android SDK (compileSdk 35), Gradle ≥ 8.9, JDK 17. Pour régénérer les binaires PHP toi-même (ex. changer de version PHP), voir la recette dans `android/README.md`.

Installation sur téléphone : transfère l'APK (câble, `adb install`, ou partage de fichier), autorise l'installation de sources inconnues, installe. APK signé en debug (parfait pour tester, pas pour le Play Store — il faudra une clé de release).

## iOS

`ios/` contient une `WKWebView` (Swift) équivalent à la coquille Android, avec un vrai pont natif (`WebAppInterface.swift`) qui expose `window.iOSNative` avec exactement les mêmes méthodes que `window.AndroidNative` — `assets/js/device.js`/`dialogs.js` détectent déjà les deux, aucun widget/service n'a besoin d'un chemin spécifique iOS. **Rien de tout ça n'est compilé ni testé** (pas de Mac/Xcode disponible dans cet environnement) et le PHP embarqué sur le device n'existe toujours pas (ça pointe pour l'instant vers un PHP hébergé sur le réseau, comme avant). Voir `ios/README.md` pour l'état exact, capacité par capacité, et les prochaines étapes.

## Limites actuelles

- iOS : pont natif écrit (parité de capacités avec Android) mais non compilé/testé, PHP toujours pas embarqué sur le device (voir `ios/README.md`)
- API de style typée (`TextSize`/`FontWeight`/`Color`) : implémentée seulement sur `Text` pour l'instant
- `StreamBuilder` fonctionne en polling HTTP (pas de WebSocket/Server-Sent Events) — suffisant pour la plupart des cas, mais pas du "temps réel" au sens strict
- Notifications push : le stockage des tokens côté backend est réel et testé (`/api/fcm/register`, `/api/fcm/count`) ; la partie Android (`FcmService.kt.example`) est écrite (y compris l'affichage de la notification système) mais **désactivée par défaut** — elle nécessite ton propre projet Firebase (`google-services.json`), qui ne peut pas être généré ici (voir le fichier `.example` pour les 6 étapes d'activation). L'envoi serveur (`Engine\Firebase\FirebaseMessaging::send()`) est maintenant implémenté, mais non testé contre un vrai projet Firebase (aucun compte de service disponible ici).
- Le serveur de dev PHP (`php -S`, utilisé aussi sur Android) est mono-thread — largement suffisant pour une app mobile (un seul client à la fois), mais pas un choix pertinent pour un vrai serveur multi-utilisateurs
- Mapbox/Google Maps (`Engine\Maps\`), `Payments\Stripe::cardElement()`, et tout `Engine\Firebase\` (Messaging/Auth/Firestore) : implémentés d'après la doc officielle, mais pas testés contre un vrai compte/projet (aucune clé/compte de service disponible dans cet environnement) — seul `OsmMap` (OpenStreetMap/Leaflet) a été vérifié en conditions réelles sur device
- Couverture de tests des widgets : `packages/ui/tests/WidgetsTest.php` ne couvre qu'une partie des widgets ; la vitrine `/widgets` prouve visuellement que le reste fonctionne (sur un vrai device), mais n'est pas un test automatisé — étendre `WidgetsTest.php` aux widgets restants est un chantier à part

## Feuille de route — chantiers pas encore commencés

**Binaire du framework façon Flutter.** Un exécutable `phpx` autonome (sans taper `php bin/phpx`) est atteignable rapidement via un `.phar` auto-exécutable (ex. avec `box`). Un vrai `phpx build android` (assemblage automatique de l'APK signé) est la suite logique une fois ce point réglé.

**Hot reload.** Éditer un écran et rafraîchir la page montre déjà le changement instantanément (PHP est interprété à chaque requête, pas de VM à recharger comme pour Dart). Ce qui manque : que la WebView se rafraîchisse **automatiquement** quand un fichier change (petit watcher de fichiers + rechargement déclenché, façon `nodemon`/`browser-sync`).

**Canvas.** Se mappe directement sur l'élément HTML5 `<canvas>` (2D, voire WebGL), mature et accéléré matériellement dans toutes les WebViews. Un widget `Canvas::make()->rect(...)->circle(...)` est réaliste à construire.

## Historique

Une première version transpilait le PHP vers Dart/Flutter (rendu Skia/Impeller réel, déployable tel quel sur les stores). Elle a été abandonnée : le code réellement exécuté sur le device était du Dart généré, pas du PHP — ne correspondait pas à l'objectif d'un framework où PHP est le runtime réel. Le code correspondant reste consultable dans l'historique git si besoin (`git log --all --oneline -- ui/`).
