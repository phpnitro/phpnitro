# Démarrage & architecture

## Prérequis

- PHP ≥ 8.1 avec Composer
- Pour builder l'APK Android : rien à installer à la main — `phpx build:android` détecte/télécharge tout seul le SDK (compileSdk 35), Gradle ≥ 8.9 et un JDK 17 si besoin. Voir [docs/mobile-builds.md](mobile-builds.md).

## Structure d'un projet

```
mon-app/
  composer.json  UN SEUL, à la racine — un seul vendor/ pour tout le projet ;
                 phpnitro/ui (le moteur natif) y est déclaré comme une
                 vraie dépendance Packagist, pas copié — database/
                 firebase/countries/... restent des `composer require`
                 séparés, opt-in, pas ajoutés par défaut
  public/        front controller (index.php, router.php) — le point d'entrée HTTP,
                  y compris /native/layout-demo (le moteur de rendu natif)
  lib/
    pages/     tes écrans natifs (Engine\Native\Widget, fourni par
               phpnitro/ui via Composer) — vide au départ, dispatchés
               par nom depuis public/index.php
    backend/   pure librairie "façon Symfony" (Controller / Entity / Repository / Service)
  android/
    app/       app Android — NativeRenderPocActivity peint sur un vrai Canvas
               (Skia), zéro WebView dans le rendu — vérifiée sur device réel ;
               dépend de com.github.phpnitro:android-engine via JitPack
               (pas de code moteur copié ici, juste la coquille app)
  ios/       pont natif Swift complet — non compilé (pas de Mac disponible)
  assets/    images, polices, audio du projet — copiés automatiquement dans public/assets
  .env       config partagée par tout le projet
```

Comme un projet Flutter (`android/`, `ios/`, `lib/`), sauf que `lib/` se scinde en `pages/` (présentation) et `backend/` (logique). Un seul `composer.json`/`vendor/` pour tout le projet — son autoload PSR-4 mappe directement `lib/pages/app` et `lib/backend/src`, plus le namespace `Engine\*` fourni par `phpnitro/ui` (Packagist). Pas de `bin/` ni de `packages/` dans un projet scaffoldé : `phpx` s'installe une seule fois, globalement (voir [docs/cli.md](cli.md)), pas par projet.

`public/index.php` délègue toute route `/api/*` à `Backend\Kernel` **dans le même processus PHP** — pas un deuxième serveur à lancer.

**Linux / macOS / Windows** : pré-alpha, disponibles via `phpx new --all` — chaque plateforme desktop a sa propre coquille native (GTK4+Cairo, Core Graphics+AppKit, WinForms+Rust), voir leurs README respectifs pour l'état réel de chacune.

## Démarrer un nouveau projet

*(Suppose un tag de version déjà publié — sinon, clone le repo et utilise `php bin/phpx` directement, voir [docs/cli.md](cli.md#installation-en-une-commande).)*

```bash
curl -fsSL https://github.com/phpnitro/phpnitro/releases/latest/download/phpx.phar -o /usr/local/bin/phpx && chmod +x /usr/local/bin/phpx   # une seule fois

phpx new mon-app
cd mon-app
composer install
phpx make:page Home
phpx serve
```

`serve` affiche un QR code pour **PhpNitro Go** (`android/go/`, une app compagnon sans code de projet) — le scanner ouvre l'écran natif réel sur un vrai device/émulateur. Un APK debug installable directement est publié à chaque tag `go-v*` (voir la [Démarrage rapide](../README.md) du dépôt pour l'URL et sa réserve honnête tant qu'aucun tag n'a encore été poussé) ; sinon, `cd android && gradle :go:assembleDebug` le build depuis ce monorepo. En attendant un device, `curl http://127.0.0.1:8090/native/layout-demo?screen=home` renvoie directement le JSON de commandes de dessin, pour vérifier que le pipeline tourne sans rendu visuel.

`phpx new` copie `lib/`, `public/`, `android/app/` (pas `android/engine/` — une dépendance JitPack à la place), `ios/`, `assets/`, `.env`, `phpnitro.yml`, et écrit un `composer.json` déclarant `phpnitro/ui` comme dépendance Packagist (pas copié) — ton nouveau projet n'est pas imbriqué dans le framework. `lib/pages/app/` arrive avec un seul écran minimal (`NativeHomeScreen.php`, juste pour ne pas planter sur la toute première requête) : `make:page Home` crée ta vraie première page (`HomePage.php`) et la remplace — `home` est un cas spécial câblé en dur (route API `/api` au lieu de `/api/home`), pas un second argument à passer.

## Écrire un écran

Un écran est une simple classe avec une méthode `build()` qui retourne un arbre de `Widget` — pas de classe de base à étendre, pas de méthode de cycle de vie à implémenter. Rien ne survit côté PHP entre deux requêtes (chaque tap est une requête HTTP indépendante) : ce qui doit persister vit dans `$_SESSION`, `Engine\Preferences\Preferences` (clé-valeur SQLite), ou une vraie base de données.

```php
<?php

namespace Engine\App;

use Engine\Native\Button;
use Engine\Native\Center;
use Engine\Native\Flex;
use Engine\Native\Scaffold;
use Engine\Native\Text;
use Engine\Native\Tokens;
use Engine\Native\Widget;
use Engine\Preferences\Preferences;

final class HomePage
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        $count = (int) Preferences::get('count', '0');

        return new Scaffold(
            new Center(Flex::column([
                new Text("Compteur : {$count}", Tokens::TEXT_TITLE, Tokens::ink()->toHex()),
                new Button('Incrémenter', 'increment'),
            ])),
            $screenWidth,
            $screenHeight,
        );
    }
}
```

Un tap sur `new Button($label, 'increment')` envoie une nouvelle requête `?action=increment` — pas de callback à écrire sur la classe elle-même. C'est `public/index.php` qui inspecte `$_GET['action']` **avant** de construire l'arbre :

```php
// public/index.php, avant new HomePage()::build(...) :
if ($action === 'increment') {
    Preferences::set('count', (string) ((int) Preferences::get('count', '0') + 1));
}
```

Voir [docs/architecture.md](architecture.md) (section "Actions") pour la table complète des préfixes d'action reconnus côté client (`navigate:`, `back`, `tab:`, `toggle:`, `device:`...) et le cycle de rendu complet.

## Navigation multi-écrans

Un tap sur `new Button('Voir le produit', 'navigate:product?id=42')` pousse un nouvel écran sur la pile — entièrement côté client (le moteur natif fait le hit-test et reconnaît le préfixe `navigate:` lui-même), aucune route à déclarer dans `public/index.php` au-delà d'un `match ($screen) { 'product' => NativeProductScreen::build(...), ... }` (ou, plus déclaratif, `Engine\Native\Router::register('product', fn () => ...)`). `back` dépile ; `tab:écran` réinitialise toute la pile (barre d'onglets).

`$_GET['id']`/`$_GET['tab']` fonctionnent normalement dans l'écran cible — `navigate:product?id=42&tab=reviews` est une vraie query string, pas des segments de chemin positionnels. Détail complet dans [docs/architecture.md](architecture.md) (section "Routes à paramètres").

Une vraie navigation (changement d'écran) joue un fondu automatique entre l'ancien et le nouveau rendu ; une action qui reste sur le même écran (comme incrémenter un compteur) ne l'anime pas.

## Formulaires

`TextField`/`Checkbox`/`Slider` (voir [docs/widgets.md](widgets.md)) gèrent leur propre saisie côté client (overlay natif au tap, `fieldValues[nom]` mis à jour à chaque frappe, renvoyé sur chaque requête suivante) — pas de soumission POST classique. Un `Button` avec l'action `"submit:écran"` referme l'overlay de saisie actif et redemande l'écran avec tous les champs inclus ; `$_GET['nomDuChamp']` les lit normalement côté PHP, exactement comme n'importe quel autre paramètre de requête.

## Voir les erreurs en développement

`APP_DEBUG=true` dans `.env` (valeur par défaut) affiche la classe, le message, le fichier/ligne et la trace complète de toute exception non gérée — y compris **dans l'app Android** : `bin/phpx bundle:android` copie `APP_DEBUG` tel quel depuis ton `.env`, il n'est plus forcé à `false`. Passe-le à `false` toi-même dans `.env` avant une vraie release (l'erreur reste alors loggée via `error_log()`, juste plus affichée).

## Base de données

`Engine\Database\Database::connection()` (Doctrine DBAL) — SQLite par défaut (`lib/backend/var/data.sqlite`, créé automatiquement), ou MySQL/PostgreSQL via `DATABASE_URL` dans `.env`. Même code, seul le DSN change. La connexion réessaie automatiquement (3 tentatives) et se reconnecte silencieusement si coupée.

### Génération de code (façon Symfony)

```bash
phpx make:page About       # lib/pages/app/AboutPage.php + Controller pairé sur /api/about
phpx make:entity Product   # lib/backend/src/Entity/Product.php + Repository
```

Structure du backend :

```
lib/backend/
  src/
    Kernel.php        dispatch route -> Controller
    Controller/       logique de requête -> réponse
    Entity/           objets métier, propriétés simples
    Repository/       accès aux données
    Service/          logique métier réutilisable
```

### Upload d'images

`POST /api/upload` avec `{"image": "data:image/png;base64,..."}` décode et stocke le fichier dans `lib/backend/var/uploads/`, `GET /api/uploads/{filename}` le ressert.

## Mode clair/sombre

Pas encore implémenté côté moteur de rendu natif (pas de `ThemeToggle`, aucun paramètre `dark` dans le protocole `/native/layout-demo`) — périmètre volontairement restreint, même limite que `ScreenClient.swift` sur iOS. `Engine\Native\Tokens` (couleurs/tailles) n'a qu'une seule palette pour l'instant.
