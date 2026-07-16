# Framework mobile PHP

Écris des interfaces mobiles modernes en PHP : des widgets (`Button`, `Text`, `Column`...) stylés avec Tailwind CSS, servis par un vrai runtime PHP, affichés dans une WebView native (Android WebView / WKWebView).

Contrairement à une première approche envisagée (transpiler le PHP vers Dart/Flutter), **PHP reste ici le vrai runtime** : ce n'est pas un langage source qui disparaît à la compilation, c'est le code qui s'exécute réellement à chaque interaction — comme le ferait un serveur web classique, mais embarqué sur le device.

## Architecture

```
backend/    app PHP "façon Symfony" (Controller / Service / Repository / Entity)
            utilise symfony/http-foundation (Request/Response) et symfony/dotenv
engine/     le moteur de widgets — classes PHP (Text, Button, Column...) qui se
            rendent en HTML + classes Tailwind, servies par le PHP intégré
android/    coquille Android (WebView native) — non vérifiée dans cet environnement,
            voir android/README.md
```

Chaque widget est une classe PHP avec un constructeur (propriétés configurables, comme dans Flutter) et une méthode `render(): string` qui produit du HTML :

```php
Button::make('Connexion');
// -> <button class="bg-blue-600 ...">Connexion</button>
```

## Prérequis

- PHP ≥ 8.1 avec Composer
- Node.js + npm (uniquement pour reconstruire le CSS Tailwind si tu changes des classes utilitaires)

## CLI (`bin/phpx`)

```bash
php bin/phpx serve             # sert engine/ sur le port 8090 (avec le router)
php bin/phpx serve:backend     # sert backend/ sur le port 8091
php bin/phpx make:screen About # crée engine/app/AboutPage.php + enregistre la route /about
php bin/phpx new mon-app       # scaffold un nouveau projet (copie engine/ + backend/)
```

`make:screen` génère la classe et l'ajoute automatiquement au routeur (`engine/public/index.php`) — pas d'étape manuelle.

## Lancer le moteur de widgets

```bash
cd engine
composer install
php -S 127.0.0.1:8090 -t public public/router.php   # ou: php ../bin/phpx serve
```

Ouvre `http://127.0.0.1:8090/` dans un navigateur — tu dois voir l'écran `engine/app/HomePage.php` rendu et stylé, avec un bouton "Incrémenter" qui augmente réellement un compteur côté serveur (état en session PHP), un lien "Réglages" (`/settings`), et un lien "Device" (`/device`) pour tester caméra/micro/localisation/vibreur.

`engine/.env` (chargé via `symfony/dotenv`) contrôle par exemple `APP_NAME`, utilisé comme `<title>` de la page.

(`public/router.php` est nécessaire avec le serveur de dev PHP pour que les routes comme `/settings` soient bien résolues par `Engine\Router` tout en continuant à servir `tailwind.css` comme fichier statique.)

### Reconstruire le CSS après avoir changé des classes Tailwind

```bash
cd engine
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

Les routes sont déclarées dans `engine/public/index.php` :

```php
$router = new Router([
    '/' => HomePage::class,
    '/settings' => SettingsPage::class,
    '/product/{id}' => ProductPage::class,
]);
```

Le widget `Link::make($label, $href)` génère un `<a href="...">` classique — navigation par vraie requête HTTP, cohérent avec le modèle "PHP est le runtime réel" (pas de routeur JS côté client). Un chemin non déclaré renvoie une vraie 404, pas une erreur silencieuse.

### Paramètres entre pages

Une route comme `/product/{id}` capture le segment dans `$this->params['id']`, accessible dans `build()` (voir `engine/app/ProductPage.php`). Chaque combinaison classe+paramètres a son propre état de session (deux visites de `/product/1` et `/product/2` ne partagent pas leur état).

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

Démo complète : `/login` (`engine/app/LoginPage.php`), identifiants `demo`/`demo`.

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
| `BottomNavigation::make([['label'=>..,'href'=>..], ...])` | `<nav>` fixée en bas d'écran |
| `FloatingActionButton::make($label, $action = null, $classes = '...')` | bouton rond flottant (bottom-right) |
| `GestureDetector::make($child, onDoubleClick:, onSwipeLeft:, onSwipeRight:)` | `<div>` détectant double-clic / swipe (déclenche une action serveur) |
| `ThemeToggle::make()` | bouton bascule clair/sombre |
| `VibrateButton::make($label, $milliseconds = 200)` | déclenche `navigator.vibrate()` |
| `LocationButton::make($label)` | déclenche `navigator.geolocation`, affiche les coordonnées |
| `CameraPreview::make($label)` | ouvre la caméra (`getUserMedia`) dans un `<video>` |
| `MicrophoneButton::make($label)` | active le micro (`getUserMedia` audio) |

Le paramètre `$classes` accepte n'importe quelle classe Tailwind — valeur par défaut sensée, entièrement remplaçable, comme un widget Flutter personnalisable.

## API de style typée (façon Flutter)

En plus de `$classes` (chaîne Tailwind libre), `Text` accepte des paramètres typés qui priment sur `$classes` dès qu'au moins un est fourni :

```php
Text::make('Titre', size: TextSize::XL2, weight: FontWeight::BOLD, color: Color::gray(900));
```

`TextSize` et `FontWeight` sont des enums (`TextSize::SM|BASE|LG|XL|XL2|XL3`, `FontWeight::NORMAL|MEDIUM|SEMIBOLD|BOLD`), `Color::gray/blue/red/green(shade)` ou `Color::of('nom', shade)` pour n'importe quelle couleur Tailwind. Ce premier jet ne couvre que `Text` — même principe applicable aux autres widgets par la suite.

## Mode clair/sombre

`ThemeToggle::make()` bascule le thème via une vraie requête POST (`_action=toggleTheme`, intercepté globalement dans `index.php`, stocké en session, appliqué comme classe `dark` sur `<html>`). Les widgets utilisent les variantes `dark:` de Tailwind (`text-gray-900 dark:text-gray-100`, etc.).

## Gestes (double-clic, swipe)

`GestureDetector` est le seul endroit du framework qui utilise du JavaScript côté client (`engine/public/gestures.js`) — nécessaire car un double-clic ou un swipe ne peut pas être détecté en HTTP pur. Le JS ne fait que déclencher la même mécanique d'action que les boutons (`fetch` POST `_action=...`), donc le serveur ne voit aucune différence entre un clic de bouton et un geste détecté.

## Capacités du device (caméra, micro, localisation, vibreur)

Widgets `VibrateButton`, `LocationButton`, `CameraPreview`, `MicrophoneButton` (écran `engine/app/DevicePage.php`, route `/device`) — utilisent les Web APIs standard (`navigator.vibrate`, `navigator.geolocation`, `navigator.mediaDevices.getUserMedia`) via `engine/public/device.js`. Ces APIs fonctionnent nativement dans une WebView Android/iOS pourvu que l'app hôte accorde les permissions nécessaires (voir `android/README.md`).

**Est-ce "vraiment natif" (comme React Native) ?** Nuance importante :
- La géolocation et le micro passent déjà par les vraies APIs natives Android en coulisses (Chromium délègue à `FusedLocationProvider`/le pipeline audio natif) — ce n'est pas simulé, juste médié par le moteur de la WebView.
- Pour la caméra et le vibreur, on va plus loin : `android/.../WebAppInterface.kt` expose un pont JS↔natif (`window.AndroidNative`) qui appelle directement `Vibrator` et lance la vraie app Camera du système (`ActivityResultContracts.TakePicturePreview`) — un vrai code natif Kotlin, pas une Web API. `device.js` préfère ce pont natif quand il est disponible (donc dans notre coquille Android) et retombe sur les Web API sinon (navigateur, iOS pas encore équivalent).
- Ce que ça ne donne *pas* encore : un flux caméra live entièrement natif avec contrôles avancés (CameraX/Camera2 complet, ISO manuel, etc.) — seulement la capture photo via l'app Camera native. Une vraie preview Camera2 custom serait une extension possible du même pont.

## Lancer le backend

```bash
cd backend
composer install
php -S 127.0.0.1:8091 -t public
```

```bash
curl http://127.0.0.1:8091/api/hello
curl http://127.0.0.1:8091/api/health
curl http://127.0.0.1:8091/api/visits   # incrémente et retourne un compteur stocké en base réelle
```

### Base de données

`Backend\Database::connection()` (Doctrine DBAL) — SQLite par défaut (`backend/var/data.sqlite`, zéro config), ou MySQL/PostgreSQL en définissant `DATABASE_URL` dans `backend/.env` (voir les exemples commentés dans ce fichier). Même code, seul le DSN change. `VisitRepository` (`src/Repository/VisitRepository.php`) démontre un vrai cycle create-table/insert/count.

Avec les deux serveurs lancés (`engine/` sur 8090 et `backend/` sur 8091), la route `/api` d'`engine/` (`engine/app/ApiPage.php`) appelle réellement `backend/` en HTTP et affiche sa réponse — les deux couches PHP communiquent entre elles pour de vrai, pas juste en théorie.

Structure façon Symfony :
```
backend/
  public/index.php        front controller (Request/Response Symfony)
  src/
    Controller/            logique de requête -> réponse (HelloController.php)
    Service/                logique métier réutilisable
    Repository/             accès aux données
    Entity/                 objets métier
  .env                      config (chargée via symfony/dotenv)
```

## Construire l'APK (PHP embarqué sur le device)

L'app Android embarque un **binaire PHP 8.4 statique** (musl, arm64, ~10 Mo, packagé en `jniLibs/arm64-v8a/libphp.so` pour pouvoir être exécuté depuis le répertoire natif en lecture seule — Android interdit l'exec depuis le stockage inscriptible). Au lancement, `PhpServer.kt` copie l'app PHP des assets vers `filesDir`, démarre `php -S 127.0.0.1:8090`, et la WebView s'y connecte : **PHP tourne réellement sur le téléphone**, pas sur un serveur distant.

```bash
php bin/phpx bundle:android   # copie engine/ (vendor --no-dev, APP_DEBUG=false) dans les assets
cd android
gradle :app:assembleDebug     # ou via Android Studio
# → android/app/build/outputs/apk/debug/app-debug.apk
```

Prérequis build : Android SDK (compileSdk 35), Gradle ≥ 8.9, JDK 17. Le binaire PHP statique vient de [static-php.dev](https://dl.static-php.dev/static-php-cli/common/) (`php-8.4.x-cli-linux-aarch64.tar.gz`, renommé `libphp.so` dans `android/app/src/main/jniLibs/arm64-v8a/` — gitignoré, à re-télécharger si absent).

Installation sur téléphone : transfère l'APK (câble, `adb install`, ou partage de fichier), autorise l'installation de sources inconnues, installe. APK signé en debug (parfait pour tester, pas pour le Play Store — il faudra une clé de release). Architecture arm64-v8a uniquement (tous les Android récents).

## Coquille Android

`android/` contient un projet Gradle minimal (`MainActivity` + `WebView`) pointé sur le serveur PHP. **Non vérifié dans cet environnement** (pas d'émulateur configuré, pas de Gradle installé) — voir `android/README.md` pour les instructions et la limite actuelle (pointe vers un PHP hébergé sur le réseau local, pas encore un PHP embarqué sur le device).

## Limites actuelles

- PHP embarqué Android : fait (binaire statique + PhpServer) mais **jamais lancé sur un vrai téléphone par moi** — l'APK est vérifié par simulation locale exacte (même bundle, binaire x86_64 jumeau) ; premier lancement réel = ton installation
- Pas encore d'équivalent iOS (WKWebView) — nécessite une machine macOS/Xcode, indisponible dans cet environnement
- Les actions serveur (`onXxx`) ne reçoivent pas encore de paramètres (seules les routes en reçoivent, via `{id}`)
- Caméra/micro/localisation/vibreur : vérifiés uniquement au niveau HTML/JS (structure correcte, Web APIs standard) — jamais testés sur un vrai device/émulateur Android faute d'environnement disponible ici
- API de style typée : implémentée seulement sur `Text` pour l'instant

## Feuille de route — chantiers pas encore commencés

Honnêtement scopés, pas improvisés :

**PHP embarqué sur le device ("100% natif").** Le plus gros morceau. Android : cross-compiler PHP (Zend Engine) via le NDK, l'embarquer dans l'APK, le lancer en sous-processus (`php -S 127.0.0.1:<port>`) au démarrage, WebView pointée sur ce localhost — faisable, pas de blocage de principe. iOS : plus dur, Apple interdit de lancer un exécutable séparé bundlé dans l'app (pas de `fork`/subprocess arbitraire) — il faudrait lier PHP comme bibliothèque (embed SAPI) appelée en-process depuis Swift/Objective-C. Piste à explorer avant de coder : le projet open source **NativePHP** (écosystème Laravel) travaille déjà sur PHP embarqué desktop et mobile — s'appuyer dessus plutôt que tout réécrire.

**Binaire du framework façon Flutter.** Deux choses différentes derrière cette demande :
- Un exécutable `phpx` autonome (sans taper `php bin/phpx`) — atteignable rapidement via un `.phar` auto-exécutable (ex. avec `box`).
- Un vrai `phpx build android/ios` produisant un APK/IPA installable et autonome — dépend entièrement du point précédent (PHP embarqué). Sans ça, "build" ne peut produire qu'une coquille WebView pointant vers un serveur externe, pas une vraie app autonome.

**Hot reload.** Plus simple qu'il n'y paraît, et potentiellement déjà "gratuit" en partie : PHP est interprété à chaque requête, donc éditer un écran et rafraîchir la page montre déjà le changement instantanément (pas de VM à recharger comme pour Dart). Ce qui manque : que la WebView se rafraîchisse **automatiquement** quand un fichier change (petit watcher de fichiers + rechargement déclenché, façon `nodemon`/`browser-sync`). Faisable en un incrément raisonnable.

**Canvas.** Se mappe directement sur l'élément HTML5 `<canvas>` (2D, voire WebGL), qui est mature et accéléré matériellement dans toutes les WebViews. Un widget `Canvas::make()->rect(...)->circle(...)` qui génère `<canvas>` + les instructions de dessin JS correspondantes est réaliste à construire.

**"Rapide comme une fusée".** Point de vigilance honnête : notre modèle actuel (clic → POST → redirect → rechargement complet de page) a un coût réseau/re-rendu par interaction que Flutter (diffing en mémoire, zéro réseau) n'a pas. Piste concrète pour combler l'écart sans tout réécrire : passer des rechargements pleine page à des mises à jour partielles (le serveur renvoie juste le HTML du widget modifié, remplacé dans le DOM via `fetch`, façon htmx/Turbo) — même modèle "PHP source de vérité", juste sans le flash de rechargement complet. Aussi : le serveur de dev PHP utilisé partout dans ce README (`php -S`) est mono-thread, pas représentatif d'un vrai déploiement (PHP-FPM + opcache en production).

## Historique

Une première version transpilait le PHP vers Dart/Flutter (rendu Skia/Impeller réel, déployable tel quel sur les stores). Elle a été abandonnée : le code réellement exécuté sur le device était du Dart généré, pas du PHP — ne correspondait pas à l'objectif d'un framework où PHP est le runtime réel. Le code correspondant reste consultable dans l'historique git si besoin (`git log --all --oneline -- ui/`).
