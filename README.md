# PhpNitro

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
```

`make:page` génère la classe et l'ajoute automatiquement au routeur (`public/index.php`), **et** génère un Controller pairé dans `lib/backend/src/Controller/`, câblé dans `Backend\Kernel` sur la route `/api/...` correspondante — façon Symfony, sans attributs de routage : juste une entrée de plus dans le `match()` de `Kernel::handle()`. `make:entity` fait la même chose côté données : une classe Entity (propriétés simples, aucune logique de persistance) pairée à un Repository pré-câblé sur `phpnitro/database`. Les deux sont des points de départ — les champs/le schéma restent à ta charge, ça retire juste la paperasse.

Par défaut, `make:page Home` (sans second argument) enregistre la route `/home`, pas `/` — passe explicitement `/` en second argument pour la page racine.

### phpnitro.yml — le manifeste de l'app

```yaml
name: Mon App
description: ...
version: 1.0.0
php: ">=8.1"

payments:
  kkiapay:
    public_key_env: KKIAPAY_PUBLIC_KEY
    secret_key_env: KKIAPAY_PRIVATE_KEY
```

Même rôle que `pubspec.yaml` pour Flutter : décrire l'app à un seul endroit plutôt que d'éparpiller ces infos. Portée volontairement limitée pour l'instant : `name` reste la source de vérité pour `APP_NAME` dans `.env` (`phpx serve`/`phpx bundle:android` le resynchronisent automatiquement à chaque lancement/build), et `payments` déclare quelles variables d'environnement chaque gateway attend — sans jamais lire les clés elles-mêmes. `phpx payments` lit ce fichier et rapporte, pour chaque gateway déclaré, s'il est configuré, en mode démo (clé publique seule) ou pas configuré du tout, directement depuis `.env`.

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

Tous les POST (boutons, formulaires, gestes) embarquent un jeton CSRF vérifié globalement — requête forgée → 419.

## Widgets disponibles

| PHP | Rend en |
|---|---|
| `Text::make($content, $classes = '...')` | `<p>` |
| `Button::make($label, $action = null, $classes = '...')` | `<button>` (ou `<form>` si `$action` est fourni) |
| `Column::make([$children], $classes = '...')` | `<div class="flex flex-col ...">` |
| `Row::make([$children], $classes = '...')` | `<div class="flex flex-row ...">` |
| `Container::make($child, $classes = '...')` | `<div>` à un seul enfant |
| `Image::make($src, $alt = '', $classes = '...')` | `<img>` |
| `Link::make($label, $href, $classes = '...')` | `<a href="...">` |
| `ListView::make([$children], $classes = '...')` | liste verticale avec séparateurs |
| `SingleScrollView::make($child, $classes = '...')` | conteneur défilable (vertical, style `overflow-y-auto`) |
| `BottomNavigation::make([['label'=>..,'href'=>..], ...])` | `<nav>` fixée en bas d'écran |
| `FloatingActionButton::make($label, $action = null, $classes = '...')` | bouton rond flottant (bottom-right) |
| `GestureDetector::make($child, onDoubleClick:, onSwipeLeft:, onSwipeRight:)` | `<div>` détectant double-clic / swipe (déclenche une action serveur) |
| `ThemeToggle::make()` | bouton bascule clair/sombre |
| `VibrateButton::make($label, $milliseconds = 200)` | déclenche `navigator.vibrate()` |
| `LocationButton::make($label)` | déclenche `navigator.geolocation`, affiche les coordonnées |
| `CameraPreview::make($label)` | ouvre la caméra (`getUserMedia`) dans un `<video>` |
| `MicrophoneButton::make($label)` | active le micro (`getUserMedia` audio) |
| `MapView::make($lat, $lng, $zoom = 15)` | carte OpenStreetMap intégrée (`<iframe>`, zéro clé API) |
| `FingerprintButton::make($label, $action)` | authentification biométrique — **native** (`BiometricPrompt` via `AndroidNative`) |
| `SoundButton::make($url, $label)` | joue un son via `MediaPlayer` natif (survit au verrouillage écran) |
| `NotifyButton::make($title, $message, $label)` | notification système native, fonctionne hors-ligne (`NotificationCompat`) |
| `PrintButton::make($label)` | imprime/exporte la page en PDF via le pipeline d'impression natif Android |
| `ImagePicker::make($fieldName, $label)` | sélecteur d'image natif (galerie système), résultat dans un champ caché de `Form` |
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
| `IconButton::make($icon, $action = null, $classes = '...', $ariaLabel = '')` | bouton icône seule (voir `Icon::*` pour le jeu d'icônes), même comportement d'action que `Button` |
| `Textarea::make($name, $label = '', $value = '', $placeholder = '', $rows = 4)` | `<textarea>` |
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
| `KkiapayButton::make($publicKey, $amount, $action, $label = 'Payer', $sandbox = true)` | paiement Kkiapay — voir [Paiement](#paiement) |
| `PaypalButton::make($clientId, $amount, $action, $currency = 'EUR')` | paiement PayPal (vrai JS SDK, `paypal.Buttons()`) — voir [Paiement](#paiement) |
| `FedapayButton::make($publicKey, $amount, $action, $description = '', $label = 'Payer', $sandbox = true)` | paiement FedaPay — voir [Paiement](#paiement) |
| `StripeButton::make($action, $label = 'Payer par carte')` + `StripeCheckout::createSessionUrl(...)` | paiement Stripe (Checkout hébergé, aucun SDK client) — voir [Paiement](#paiement) |
| `FeexpayButton::make($shopId, $amount, $action, $label = 'Payer', $sandbox = true)` | paiement Feexpay — gabarit non vérifié, voir [Paiement](#paiement) |
| `IziChangePayButton::make($apiKey, $amount, $action, $label = 'Payer', $sandbox = true)` | paiement iZiChangePay — gabarit non vérifié, voir [Paiement](#paiement) |
| `TresorPayButton::make($apiKey, $amount, $action, $label = 'Payer', $sandbox = true)` | paiement TresorPay — gabarit non vérifié, voir [Paiement](#paiement) |

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

Sept gateways ont un widget dans `packages/ui/src/` (voir [Widgets disponibles](#widgets-disponibles)) et une intégration complète et testée dans `examples/ecom` (`CheckoutPage`). Toutes suivent la même règle : un événement de succès côté client (`addSuccessListener`, `onApprove`...) **n'est jamais une preuve de paiement**, juste un signal d'UI — la commande n'est créée qu'après une vérification serveur-à-serveur avec la clé **privée/secrète**. La confiance dans chaque implémentation varie beaucoup d'un gateway à l'autre :

| Gateway | Confiance | Ce qui est vérifié |
|---|---|---|
| **Kkiapay** | Élevée | Pattern d'origine — widget JS + `transaction_id` + endpoint de vérification documenté. Non testé contre un vrai compte sandbox. |
| **PayPal** | Élevée | Vrai JS SDK (`paypal.Buttons()`), flux OAuth2 + capture server-side standard et bien documenté. Non testé contre une vraie app sandbox. |
| **FedaPay** | Moyenne-élevée | Même forme que Kkiapay (`FedaPay.init()`). Non testé. |
| **Stripe** | Élevée sur le principe, non testé sur l'appel réel | Checkout hébergé (`StripeCheckout::createSessionUrl()`, API REST directe, aucun SDK client nécessaire) — confirmé qu'une clé invalide échoue proprement (pas de crash), l'appel réel à l'API Stripe n'a pas pu être testé (pas de clé sandbox disponible ici). |
| **Feexpay, iZiChangePay, TresorPay** | Faible à très faible | Gabarits structurels seulement (URL de script et nom des fonctions JS marqués `TODO`, à vérifier contre la doc de chaque gateway) — `CheckoutPage` refuse la transaction dès qu'une clé secrète est configurée plutôt que de faire semblant de la vérifier avec un appel non confirmé. |

`examples/ecom/.env` documente les variables de chaque gateway ; `/checkout` choisit le **premier** gateway configuré dans cet ordre (voir `CheckoutPage::selectPaymentWidget()`) — rien de configuré = mode démo (comportement d'avant, commande créée directement sans paiement). Quand le bouton est placé à l'intérieur d'un `Form::make(...)`, son callback de succès sérialise aussi les autres champs du formulaire (nom, adresse...) et les poste avec l'identifiant de transaction — utile pour un vrai checkout qui a besoin des infos de livraison en plus du paiement (voir `KkiapayButton`).

Pour ajouter un autre gateway : `packages/ui/src/KkiapayButton.php` ou `FedapayButton.php` servent de modèle pour un widget JS classique ; `StripeButton.php`/`StripeCheckout.php` pour un flux de redirection hébergé sans SDK client.

## Mode clair/sombre

`ThemeToggle::make()` bascule le thème via une vraie requête POST (`_action=toggleTheme`, intercepté globalement dans `index.php`, stocké en session, appliqué comme classe `dark` sur `<html>`). Les widgets utilisent les variantes `dark:` de Tailwind (`text-gray-900 dark:text-gray-100`, etc.).

## Gestes (double-clic, swipe)

`GestureDetector` est le seul endroit du framework qui utilise du JavaScript côté client (`assets/js/gestures.js`) — nécessaire car un double-clic ou un swipe ne peut pas être détecté en HTTP pur. Le JS ne fait que déclencher la même mécanique d'action que les boutons (`fetch` POST `_action=...`), donc le serveur ne voit aucune différence entre un clic de bouton et un geste détecté.

## Capacités du device (caméra, micro, localisation, vibreur, biométrie, notifications, son, impression)

Widgets `VibrateButton`, `LocationButton`, `CameraPreview`, `MicrophoneButton`, `FingerprintButton`, `SoundButton`, `NotifyButton`, `PrintButton`, `ImagePicker` (écran `lib/pages/app/DevicePage.php`, route `/device`) — pilotés par `assets/js/device.js`, qui **préfère toujours le pont natif** (`window.AndroidNative`, exposé par `android/.../WebAppInterface.kt`) et ne retombe sur les Web APIs standard que si ce pont est absent (navigateur, tests locaux).

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

`ios/` contient un stub `WKWebView` (Swift) équivalent à la coquille Android — **non testé** (pas de Mac/Xcode disponible dans cet environnement de développement), et sans PHP embarqué sur le device (ça pointe pour l'instant vers un PHP hébergé sur le réseau). Voir `ios/README.md` pour l'état exact et les prochaines étapes.

## Limites actuelles

- `nav.js` (navigation sans rechargement de page) : vérifié en profondeur via curl (les deux projets, tous les cas listés ci-dessus) mais pas encore reconfirmé dans la WebView réelle après ce changement précis (téléphone déconnecté au moment d'écrire ceci) — à revérifier visuellement à la prochaine connexion.
- iOS : stub non testé, pas de PHP embarqué (voir `ios/README.md`)
- API de style typée (`TextSize`/`FontWeight`/`Color`) : implémentée seulement sur `Text` pour l'instant
- `StreamBuilder` fonctionne en polling HTTP (pas de WebSocket/Server-Sent Events) — suffisant pour la plupart des cas, mais pas du "temps réel" au sens strict
- Notifications push : le stockage des tokens côté backend est réel et testé (`/api/fcm/register`, `/api/fcm/count`) ; la partie Android (`FcmService.kt.example`) est écrite mais **désactivée par défaut** — elle nécessite ton propre projet Firebase (`google-services.json`), qui ne peut pas être généré ici (voir le fichier `.example` pour les 6 étapes d'activation). L'envoi effectif de notifications (Firebase Admin SDK côté serveur) n'est pas implémenté.
- Le serveur de dev PHP (`php -S`, utilisé aussi sur Android) est mono-thread — largement suffisant pour une app mobile (un seul client à la fois), mais pas un choix pertinent pour un vrai serveur multi-utilisateurs

## Feuille de route — chantiers pas encore commencés

**Binaire du framework façon Flutter.** Un exécutable `phpx` autonome (sans taper `php bin/phpx`) est atteignable rapidement via un `.phar` auto-exécutable (ex. avec `box`). Un vrai `phpx build android` (assemblage automatique de l'APK signé) est la suite logique une fois ce point réglé.

**Hot reload.** Éditer un écran et rafraîchir la page montre déjà le changement instantanément (PHP est interprété à chaque requête, pas de VM à recharger comme pour Dart). Ce qui manque : que la WebView se rafraîchisse **automatiquement** quand un fichier change (petit watcher de fichiers + rechargement déclenché, façon `nodemon`/`browser-sync`).

**Canvas.** Se mappe directement sur l'élément HTML5 `<canvas>` (2D, voire WebGL), mature et accéléré matériellement dans toutes les WebViews. Un widget `Canvas::make()->rect(...)->circle(...)` est réaliste à construire.

## Historique

Une première version transpilait le PHP vers Dart/Flutter (rendu Skia/Impeller réel, déployable tel quel sur les stores). Elle a été abandonnée : le code réellement exécuté sur le device était du Dart généré, pas du PHP — ne correspondait pas à l'objectif d'un framework où PHP est le runtime réel. Le code correspondant reste consultable dans l'historique git si besoin (`git log --all --oneline -- ui/`).
