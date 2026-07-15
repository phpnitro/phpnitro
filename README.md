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

## Lancer le moteur de widgets

```bash
cd engine
composer install
php -S 127.0.0.1:8090 -t public
```

Ouvre `http://127.0.0.1:8090/` dans un navigateur — tu dois voir l'écran `engine/app/HomePage.php` rendu et stylé, avec un bouton "Incrémenter" qui augmente réellement un compteur côté serveur (état en session PHP).

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

## Widgets disponibles

| PHP | Rend en |
|---|---|
| `Text::make($content, $classes = '...')` | `<p>` |
| `Button::make($label, $action = null, $classes = '...')` | `<button>` (ou `<form>` si `$action` est fourni) |
| `Column::make([$children], $classes = '...')` | `<div class="flex flex-col ...">` |
| `Row::make([$children], $classes = '...')` | `<div class="flex flex-row ...">` |
| `Container::make($child, $classes = '...')` | `<div>` à un seul enfant |
| `Image::make($src, $alt = '', $classes = '...')` | `<img>` |

Le paramètre `$classes` accepte n'importe quelle classe Tailwind — valeur par défaut sensée, entièrement remplaçable, comme un widget Flutter personnalisable.

## Lancer le backend

```bash
cd backend
composer install
php -S 127.0.0.1:8091 -t public
```

```bash
curl http://127.0.0.1:8091/api/hello
curl http://127.0.0.1:8091/api/health
```

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

## Coquille Android

`android/` contient un projet Gradle minimal (`MainActivity` + `WebView`) pointé sur le serveur PHP. **Non vérifié dans cet environnement** (pas d'émulateur configuré, pas de Gradle installé) — voir `android/README.md` pour les instructions et la limite actuelle (pointe vers un PHP hébergé sur le réseau local, pas encore un PHP embarqué sur le device).

## Limites actuelles

- Pas de PHP embarqué *sur* le device — c'est le plus gros chantier restant (cross-compiler PHP pour Android/iOS)
- Pas encore d'équivalent iOS (WKWebView) — nécessite une machine macOS/Xcode, indisponible dans cet environnement
- Pas de navigation multi-écrans
- `Screen`/actions : un seul niveau d'action par clic, pas de paramètres passés à l'action pour l'instant

## Historique

Une première version transpilait le PHP vers Dart/Flutter (rendu Skia/Impeller réel, déployable tel quel sur les stores). Elle a été abandonnée : le code réellement exécuté sur le device était du Dart généré, pas du PHP — ne correspondait pas à l'objectif d'un framework où PHP est le runtime réel. Le code correspondant reste consultable dans l'historique git si besoin (`git log --all --oneline -- ui/`).
