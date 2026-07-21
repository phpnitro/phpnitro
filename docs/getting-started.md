# Démarrage & architecture

## Prérequis

- PHP ≥ 8.1 avec Composer
- Node.js + npm (uniquement pour reconstruire le CSS Tailwind si tu changes des classes utilitaires)
- Pour builder l'APK Android : Android SDK (compileSdk 35), Gradle ≥ 8.9, JDK 17 — voir [docs/mobile-builds.md](mobile-builds.md)

## Structure d'un projet

```
mon-app/
  composer.json  UN SEUL, à la racine — un seul vendor/ pour tout le projet
  public/        front controller (index.php, router.php, tailwind.css) — le point d'entrée HTTP
  lib/
    pages/     tes écrans (Screen) — vide au départ, tu les crées avec `phpx make:page`
    backend/   pure librairie "façon Symfony" (Controller / Entity / Repository / Service)
  packages/
    ui/src/         le widget SDK (Text, Button, Column...) — phpnitro/ui
    database/src/   connexion base de données — phpnitro/database
    ... (device, payments, maps, dialogs, firebase, countries, preferences,
         connectivity, launcher, diagnostics, format, socialauth)
  android/   app Android (WebView native + PHP embarqué) — vérifiée sur device réel
  ios/       pont natif Swift complet — non compilé (pas de Mac disponible)
  assets/    images, polices, JS du framework — copiés automatiquement dans public/assets
  .env       config partagée par tout le projet
  bin/       phpx (CLI)
```

Comme un projet Flutter (`android/`, `ios/`, `lib/`), sauf que `lib/` se scinde en `pages/` (présentation) et `backend/` (logique). Un seul `composer.json`/`vendor/` pour tout le projet — son autoload PSR-4 mappe directement chaque `packages/*/src`, `lib/pages/app` et `lib/backend/src`.

`public/index.php` délègue toute route `/api/*` à `Backend\Kernel` **dans le même processus PHP** — pas un deuxième serveur à lancer.

**Linux / macOS / Windows** : pas implémentés — chaque plateforme desktop demanderait sa propre coquille native (GTK+WebKit, Cocoa+WKWebView, WebView2).

## Démarrer un nouveau projet

```bash
php bin/phpx new mon-app
cd mon-app
composer install
bin/phpx make:page Home /
bin/phpx serve
```

`phpx new` copie `lib/`, `packages/`, `public/`, `android/` (binaires PHP inclus), `ios/`, `assets/`, `bin/`, `.env`, `composer.json` et `package.json` depuis ce dépôt vers l'emplacement de ton choix — ton nouveau projet n'est pas imbriqué dans le framework. `lib/pages/app/` arrive **vide** : `make:page Home /` crée ta première page et la route racine.

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
