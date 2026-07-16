# Framework mobile PHP

Écris des interfaces mobiles modernes en PHP : des widgets (`Button`, `Text`, `Column`...) stylés avec Tailwind CSS, servis par un vrai runtime PHP, affichés dans une WebView native (Android WebView / WKWebView).

Contrairement à une première approche envisagée (transpiler le PHP vers Dart/Flutter), **PHP reste ici le vrai runtime** : ce n'est pas un langage source qui disparaît à la compilation, c'est le code qui s'exécute réellement à chaque interaction — comme le ferait un serveur web classique, mais embarqué sur le device. **Vérifié de bout en bout sur un vrai téléphone Android** (voir `android/README.md`).

**Exemple complet** : `examples/ecom` — une boutique en ligne (catalogue, panier, checkout, compte, carte, biométrie, suivi de commande en direct) qui utilise la quasi-totalité du framework. Voir `examples/ecom/README.md`.

## Architecture d'un projet

```
mon-app/
  ui/        widgets PHP (Text, Button, Column...) + tes écrans (Screen)
  backend/   API "façon Symfony" (Controller / Service / Repository / Entity)
  android/   app Android (WebView native + PHP embarqué) — vérifiée sur device réel
  ios/       stub WKWebView — non testé (pas de Mac/Xcode disponible ici)
  assets/    images, polices — copié automatiquement dans ui/public/assets
  .env       config partagée par ui/ et backend/ (un seul fichier, à la racine)
  bin/       phpx (CLI)
```

`ui/` et `backend/` sont servis par le **même processus PHP** — pas un deuxième serveur à lancer, c'est implicite (voir "Backend" plus bas). C'est cette structure exacte que génère `phpx new`.

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
composer install --working-dir=ui
composer install --working-dir=backend
bin/phpx serve
```

Ouvre `http://127.0.0.1:8090/`. `phpx new` copie `ui/`, `backend/`, `android/` (binaires PHP inclus), `ios/`, `assets/`, `bin/` et `.env` depuis ce dépôt vers l'emplacement de ton choix (comme `composer create-project`) — ton nouveau projet n'est pas imbriqué dans le framework.

## CLI (`bin/phpx`)

```bash
php bin/phpx serve             # sert ui/ + backend/ ensemble sur le port 8090
php bin/phpx serve:backend     # sert backend/ seul sur le port 8091 (dev API isolée)
php bin/phpx make:screen About # crée ui/app/AboutPage.php + enregistre la route /about
php bin/phpx new mon-app       # scaffold un nouveau projet complet (voir ci-dessus)
php bin/phpx bundle:android    # copie ui/ + backend/ + .env dans l'app Android
```

`make:screen` génère la classe et l'ajoute automatiquement au routeur (`ui/public/index.php`) — pas d'étape manuelle.

### Tester la CLI

```bash
bash bin/test.sh
```

Ce script fait un vrai `phpx new` dans un dossier temporaire, un vrai `composer install`, un vrai `make:screen`, lance un vrai serveur et l'interroge en HTTP (`/`, `/api/hello`, la route générée), puis un vrai `bundle:android` — il vérifie le résultat réel de chaque commande, pas des mocks. Base-toi dessus pour valider un changement dans `bin/phpx`.

Pour les widgets/Screen/Router/Csrf, la suite PHPUnit (`cd ui && vendor/bin/phpunit`) couvre le rendu et la logique unitairement.

## Lancer le moteur de widgets

```bash
cd ui
composer install
php -S 127.0.0.1:8090 -t public public/router.php   # ou: php ../bin/phpx serve
```

Tu dois voir l'écran `ui/app/HomePage.php` rendu et stylé, avec un bouton "Incrémenter" qui augmente réellement un compteur côté serveur (état en session PHP), un lien "Réglages" (`/settings`), et un lien "Device" (`/device`) pour tester caméra/micro/localisation/vibreur.

`.env` (à la racine du projet, chargé via `symfony/dotenv`) contrôle par exemple `APP_NAME`, utilisé comme `<title>` de la page — un seul fichier, partagé par `ui/` et `backend/`.

(`public/router.php` est nécessaire avec le serveur de dev PHP pour que les routes comme `/settings` soient bien résolues par `Engine\Router` tout en continuant à servir `tailwind.css` comme fichier statique.)

### Reconstruire le CSS après avoir changé des classes Tailwind

```bash
cd ui
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

Les routes sont déclarées dans `ui/public/index.php` :

```php
$router = new Router([
    '/' => HomePage::class,
    '/settings' => SettingsPage::class,
    '/product/{id}' => ProductPage::class,
]);
```

Le widget `Link::make($label, $href)` génère un `<a href="...">` classique — navigation par vraie requête HTTP, cohérent avec le modèle "PHP est le runtime réel" (pas de routeur JS côté client). Un chemin non déclaré renvoie une vraie 404, pas une erreur silencieuse.

### Paramètres entre pages

Une route comme `/product/{id}` capture le segment dans `$this->params['id']`, accessible dans `build()` (voir `ui/app/ProductPage.php`). Chaque combinaison classe+paramètres a son propre état de session (deux visites de `/product/1` et `/product/2` ne partagent pas leur état).

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

Démo complète : `/login` (`ui/app/LoginPage.php`), identifiants `demo`/`demo`.

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

`GestureDetector` est le seul endroit du framework qui utilise du JavaScript côté client (`ui/public/gestures.js`) — nécessaire car un double-clic ou un swipe ne peut pas être détecté en HTTP pur. Le JS ne fait que déclencher la même mécanique d'action que les boutons (`fetch` POST `_action=...`), donc le serveur ne voit aucune différence entre un clic de bouton et un geste détecté.

## Capacités du device (caméra, micro, localisation, vibreur, biométrie, notifications, son, impression)

Widgets `VibrateButton`, `LocationButton`, `CameraPreview`, `MicrophoneButton`, `FingerprintButton`, `SoundButton`, `NotifyButton`, `PrintButton`, `ImagePicker` (écran `ui/app/DevicePage.php`, route `/device`) — pilotés par `ui/public/device.js`, qui **préfère toujours le pont natif** (`window.AndroidNative`, exposé par `android/.../WebAppInterface.kt`) et ne retombe sur les Web APIs standard que si ce pont est absent (navigateur, tests locaux).

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

`ui/` et `backend/` (Symfony HttpFoundation) sont servis par le **même processus PHP** — `ui/public/index.php` délègue toute route `/api/*` à `Backend\Kernel`, en mémoire (pas de requête réseau). Zéro configuration supplémentaire, y compris dans l'app Android : le backend est toujours disponible, implicitement.

```bash
curl http://127.0.0.1:8090/api/hello
curl http://127.0.0.1:8090/api/health
curl http://127.0.0.1:8090/api/visits   # incrémente et retourne un compteur stocké en base réelle
```

Pour développer l'API seule sans l'UI : `php bin/phpx serve:backend` (port 8091 indépendant).

### Upload d'images

`POST /api/upload` avec `{"image": "data:image/png;base64,..."}` (exactement ce que produit le widget `ImagePicker`) décode et stocke le fichier dans `backend/var/uploads/`, et `GET /api/uploads/{filename}` le ressert avec le bon `Content-Type` (déterminé par extension, sans dépendre de `ext-fileinfo` — pas garanti présent dans le PHP cross-compilé pour Android). Voir `Backend\Service\ImageUploadService`.

### Base de données

`Backend\Database::connection()` (Doctrine DBAL) — SQLite par défaut (`backend/var/data.sqlite`, créé automatiquement au premier appel, zéro config), ou MySQL/PostgreSQL en définissant `DATABASE_URL` dans `.env` (voir les exemples commentés dans ce fichier). Même code, seul le DSN change. `VisitRepository` (`backend/src/Repository/VisitRepository.php`) démontre un vrai cycle create-table/insert/count. `libsqlite3.so` est embarqué avec le binaire PHP dans l'app Android (voir plus bas).

La connexion réessaie automatiquement (3 tentatives, avec backoff) à l'établissement initial, et se reconnecte silencieusement si une connexion déjà ouverte a été coupée en cours de session (utile avec un `DATABASE_URL` distant) — un appelant récupère toujours une connexion utilisable plutôt qu'une exception PDO brute.

Structure façon Symfony :
```
backend/
  public/index.php        front controller autonome (dev API isolée, `serve:backend`)
  src/
    Kernel.php             dispatch route -> Controller, réutilisé par ui/public/index.php
    Controller/            logique de requête -> réponse (HelloController.php)
    Service/                logique métier réutilisable
    Repository/             accès aux données
    Entity/                 objets métier
```

## Construire l'APK (PHP embarqué sur le device)

L'app Android embarque un **vrai PHP 8.4 cross-compilé pour Android** (via le NDK, deux architectures : `armeabi-v7a` et `arm64-v8a`, déjà présentes dans `android/app/src/main/jniLibs/` — **aucun Docker ni compilation requise** pour builder l'app). Au lancement, `PhpServer.kt` copie l'app PHP des assets vers `filesDir`, démarre le binaire embarqué sur un **port choisi dynamiquement** (évite tout conflit si plusieurs apps construites avec ce framework tournent sur le même device), et la WebView s'y connecte : **PHP tourne réellement sur le téléphone**, pas sur un serveur distant. **Vérifié de bout en bout** sur un Infinix X6532 (Android 14, `armeabi-v7a`) — y compris l'authentification biométrique native.

Un vrai **splash screen natif** (Android 12+ SplashScreen API) reste affiché exactement jusqu'à ce que le serveur PHP ait démarré et que la page ait fini de charger — pas de délai fixe deviné, pas de flash d'écran blanc pendant le démarrage. Icône d'app adaptative incluse (vectorielle, thème bleu/éclair).

```bash
php bin/phpx bundle:android   # copie ui/ + backend/ (vendor --no-dev) + .env (APP_DEBUG=false) dans les assets
cd android
gradle :app:assembleDebug     # ou via Android Studio
# → android/app/build/outputs/apk/debug/app-debug.apk
```

Prérequis build : Android SDK (compileSdk 35), Gradle ≥ 8.9, JDK 17. Pour régénérer les binaires PHP toi-même (ex. changer de version PHP), voir la recette dans `android/README.md`.

Installation sur téléphone : transfère l'APK (câble, `adb install`, ou partage de fichier), autorise l'installation de sources inconnues, installe. APK signé en debug (parfait pour tester, pas pour le Play Store — il faudra une clé de release).

## iOS

`ios/` contient un stub `WKWebView` (Swift) équivalent à la coquille Android — **non testé** (pas de Mac/Xcode disponible dans cet environnement de développement), et sans PHP embarqué sur le device (ça pointe pour l'instant vers un PHP hébergé sur le réseau). Voir `ios/README.md` pour l'état exact et les prochaines étapes.

## Limites actuelles

- iOS : stub non testé, pas de PHP embarqué (voir `ios/README.md`)
- API de style typée (`TextSize`/`FontWeight`/`Color`) : implémentée seulement sur `Text` pour l'instant
- `StreamBuilder` fonctionne en polling HTTP (pas de WebSocket/Server-Sent Events) — suffisant pour la plupart des cas, mais pas du "temps réel" au sens strict
- Notifications push : le stockage des tokens côté backend est réel et testé (`/api/fcm/register`, `/api/fcm/count`) ; la partie Android (`FcmService.kt.example`) est écrite mais **désactivée par défaut** — elle nécessite ton propre projet Firebase (`google-services.json`), qui ne peut pas être généré ici (voir le fichier `.example` pour les 6 étapes d'activation). L'envoi effectif de notifications (Firebase Admin SDK côté serveur) n'est pas implémenté.
- Le serveur de dev PHP (`php -S`, utilisé aussi sur Android) est mono-thread — largement suffisant pour une app mobile (un seul client à la fois), mais pas un choix pertinent pour un vrai serveur multi-utilisateurs

## Feuille de route — chantiers pas encore commencés

**Binaire du framework façon Flutter.** Un exécutable `phpx` autonome (sans taper `php bin/phpx`) est atteignable rapidement via un `.phar` auto-exécutable (ex. avec `box`). Un vrai `phpx build android` (assemblage automatique de l'APK signé) est la suite logique une fois ce point réglé.

**Hot reload.** Éditer un écran et rafraîchir la page montre déjà le changement instantanément (PHP est interprété à chaque requête, pas de VM à recharger comme pour Dart). Ce qui manque : que la WebView se rafraîchisse **automatiquement** quand un fichier change (petit watcher de fichiers + rechargement déclenché, façon `nodemon`/`browser-sync`).

**Canvas.** Se mappe directement sur l'élément HTML5 `<canvas>` (2D, voire WebGL), mature et accéléré matériellement dans toutes les WebViews. Un widget `Canvas::make()->rect(...)->circle(...)` est réaliste à construire.

**"Rapide comme une fusée".** Notre modèle actuel (clic → POST → redirect → rechargement complet de page) a un coût réseau/re-rendu par interaction que Flutter (diffing en mémoire, zéro réseau) n'a pas. Piste concrète : passer des rechargements pleine page à des mises à jour partielles (le serveur renvoie juste le HTML du widget modifié, remplacé dans le DOM via `fetch`, façon htmx/Turbo) — même modèle "PHP source de vérité", juste sans le flash de rechargement complet.

## Historique

Une première version transpilait le PHP vers Dart/Flutter (rendu Skia/Impeller réel, déployable tel quel sur les stores). Elle a été abandonnée : le code réellement exécuté sur le device était du Dart généré, pas du PHP — ne correspondait pas à l'objectif d'un framework où PHP est le runtime réel. Le code correspondant reste consultable dans l'historique git si besoin (`git log --all --oneline -- ui/`).
