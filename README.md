# Framework mobile PHP

Écris des interfaces mobiles modernes en PHP : des widgets (`Button`, `Text`, `Column`...) stylés avec Tailwind CSS, servis par un vrai runtime PHP, affichés dans une WebView native (Android WebView / WKWebView).

Contrairement à une première approche envisagée (transpiler le PHP vers Dart/Flutter), **PHP reste ici le vrai runtime** : ce n'est pas un langage source qui disparaît à la compilation, c'est le code qui s'exécute réellement à chaque interaction — comme le ferait un serveur web classique, mais embarqué sur le device.

## Architecture

```
backend/    app PHP "façon Symfony" (Controller / Service / Repository / Entity)
            utilise symfony/http-foundation (Request/Response) et symfony/dotenv
engine/     le moteur de widgets — classes PHP (Text, Button, Column...) qui se
            rendent en HTML + classes Tailwind, servies par le PHP intégré
```

Chaque widget est une classe PHP avec un constructeur (propriétés configurables, comme dans Flutter) et une méthode `render(): string` qui produit du HTML :

```php
Button::make('Connexion');
// -> <button class="bg-blue-600 ...">Connexion</button>
```

## Prérequis

- PHP ≥ 8.1 avec Composer
- Accès internet (pour le moment : Tailwind est chargé via CDN — une version compilée/offline est prévue pour l'usage mobile réel)

## Lancer le spike du moteur de widgets

```bash
cd engine
composer install
php -S 127.0.0.1:8090 -t public
```

Puis ouvre `http://127.0.0.1:8090/` dans un navigateur — tu dois voir l'écran `engine/app/HomePage.php` rendu et stylé.

## Écrire un écran

```php
<?php

namespace Engine\App;

use Engine\Button;
use Engine\Column;
use Engine\Text;
use Engine\Widget;

final class HomePage
{
    public function build(): Widget
    {
        return Column::make([
            Text::make('Mon application', 'text-2xl font-bold text-gray-900'),
            Button::make('Connexion'),
        ]);
    }
}
```

## Widgets disponibles

| PHP | Rend en |
|---|---|
| `Text::make($content, $classes = '...')` | `<p>` |
| `Button::make($label, $classes = '...')` | `<button>` |
| `Column::make([$children], $classes = '...')` | `<div class="flex flex-col ...">` |

Le deuxième argument (`$classes`) accepte n'importe quelle classe Tailwind — il a une valeur par défaut sensée mais peut être entièrement remplacé, exactement comme un `style` ou un widget Flutter personnalisable.

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

## Limites actuelles (spike)

- Tailwind chargé via CDN (nécessite internet) — une version compilée/embarquée est nécessaire avant tout usage mobile réel hors-ligne
- Pas encore d'interactivité (clic sur `Button` ne fait rien pour l'instant) ni de gestion d'état
- Pas encore embarqué dans une vraie WebView Android/iOS — testé pour l'instant via navigateur desktop / serveur PHP intégré
- Pas de widgets `Row`, `Container`, `Image`, navigation multi-écrans (prochaines étapes)

## Historique

Une première version transpilait le PHP vers Dart/Flutter (rendu Skia/Impeller réel, déployable tel quel sur les stores). Elle a été abandonnée : le code réellement exécuté sur le device était du Dart généré, pas du PHP — ne correspondait pas à l'objectif d'un framework où PHP est le runtime réel. Le code correspondant reste consultable dans l'historique git si besoin (`git log --all --oneline -- ui/`).
