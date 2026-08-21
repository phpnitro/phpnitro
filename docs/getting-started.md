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

```bash
curl -fsSL https://github.com/phpnitro/phpnitro/releases/latest/download/phpx.phar -o /usr/local/bin/phpx && chmod +x /usr/local/bin/phpx   # une seule fois

phpx new mon-app
cd mon-app
composer install
phpx make:page Home /
phpx serve
```

`phpx new` copie `lib/`, `public/`, `android/app/` (pas `android/engine/` — une dépendance JitPack à la place), `ios/`, `assets/`, `.env`, `phpnitro.yml`, et écrit un `composer.json` déclarant `phpnitro/ui` comme dépendance Packagist (pas copié) — ton nouveau projet n'est pas imbriqué dans le framework. `lib/pages/app/` arrive **vide** : `make:page Home /` crée ta première page et la route racine.

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

Un clic sur `Button::make($label, action: 'increment')` soumet un POST (`_action=increment`), qui appelle `onIncrement()`, sauvegarde le nouvel état en session, puis redirige (POST-redirect-GET, pas de resoumission au refresh).

## Navigation multi-écrans

Les routes sont déclarées dans `public/index.php` :

```php
$router = new Router([
    '/' => HomePage::class,
    '/settings' => SettingsPage::class,
    '/product/{id}' => ProductPage::class,
]);
```

`Link::make($label, $href)` génère un `<a href="...">` classique — une vraie route HTTP, résolue par `Engine\Router`. Un chemin non déclaré renvoie une vraie 404.

Une route comme `/product/{id}` capture le segment dans `$this->params['id']`. Chaque combinaison classe+paramètres a son propre état de session.

### Pas de rechargement de page (`nav.js`)

Cliquer sur un `Link` ou soumettre un `Form`/`Button` ne recharge **pas** la page : `assets/js/nav.js` intercepte le clic/la soumission, fait la requête avec un header (`X-Phpx-Partial: 1`), et remplace le contenu par la réponse au lieu de naviguer — `history.pushState` tient l'URL à jour. PHP reste l'unique source de rendu : le serveur renvoie le même arbre de widgets, juste encapsulé dans un petit JSON (`{html, path, theme}`) au lieu du document complet.

Une vraie navigation (changement de route) joue un fondu (`.phpx-page-enter`) ; une action qui reste sur la même route (comme incrémenter un compteur) ne l'anime pas — évite l'effet de "petit rechargement" sur chaque clic.

Sans JS (curl, `bin/test.sh`) : comportement classique, POST → redirection 303 → GET, document complet à chaque fois — rien ne casse, l'amélioration est strictement progressive.

Sur Android, `history.pushState` alimente aussi la pile back/forward native de la WebView — le bouton retour matériel la parcourt avant de fermer l'Activity.

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
        return '/';
    }
    $this->state['error'] = 'Identifiants invalides.';
    return null;
}
```

Démo complète : `/login`, identifiants `demo`/`demo`.

`ErrorBanner::make($this->state['error'])` affiche une erreur explicitement (icône + fond rouge), sûr même si `null`. `TextField`/`Textarea`/`SelectBox` acceptent aussi un `error: string` optionnel. `Stepper` structure un assistant multi-étapes stateless (l'index et les données vivent dans `$this->state` de l'écran appelant).

Tous les POST embarquent un jeton CSRF vérifié globalement — requête forgée → 419.

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

`ThemeToggle::make()` bascule le thème via une vraie requête POST, stockée en session, appliquée comme classe `dark` sur `<html>`. Les widgets utilisent les variantes `dark:` de Tailwind.
