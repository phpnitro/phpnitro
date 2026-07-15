# PHP → Flutter Transpiler

Écris ton UI mobile en PHP, avec une syntaxe fluide façon Flutter (`Column::new([...])`), et obtiens une vraie application Flutter compilée : rendu natif (Skia/Impeller), déployable sur Play Store / App Store sans rustine, parce que le binaire final **est** une vraie app Flutter.

Le transpileur ne réinvente aucun moteur de rendu : il traduit ton code PHP en Dart (analyse d'AST statique via `nikic/php-parser`), puis délègue tout le reste (rendu, build, packaging) à la toolchain Flutter officielle.

## Prérequis

- PHP ≥ 8.1 avec Composer
- Flutter SDK (installé et dans le `PATH`)
- Un device : `flutter devices` doit lister au moins `linux`, `chrome`, un émulateur Android ou un simulateur iOS

Vérifier :
```bash
php -v
flutter doctor
flutter devices
```

## Installation

```bash
cd transpiler
composer install
```

## Lancer un exemple existant

Deux exemples sont fournis dans `examples/` :

- `examples/demo` — écran statique (`StatelessWidget`)
- `examples/counter` — bouton interactif avec état (`StatefulWidget`, `onPressed`, `setState`)

```bash
cd examples/counter
php ../../transpiler/bin/phpx run linux
```

Remplace `linux` par un autre device Flutter (`chrome`, un id d'émulateur Android, etc. — voir `flutter devices`).

Cette commande :
1. lit `app/HomePage.php` ;
2. le transpile en Dart et régénère `.flutter/lib/main.dart` ;
3. lance `flutter run -d <device>` sur le projet Flutter généré dans `.flutter/`.

Appuie sur `q` dans le terminal pour quitter.

## Créer une nouvelle app

```bash
cd examples   # ou n'importe quel dossier parent de ton choix
php ../transpiler/bin/phpx create mon_app
```

Ça crée :
```
mon_app/
  app/HomePage.php     <- le fichier PHP que tu édites
  .flutter/            <- le vrai projet Flutter généré (ne pas éditer à la main, il est régénéré à chaque `phpx run`)
```

Édite `mon_app/app/HomePage.php`, puis :
```bash
cd mon_app
php ../../transpiler/bin/phpx run linux
```

## Écrire un écran

### Statique (`StatelessWidget`)

```php
<?php

use Sdk\StatelessWidget;
use Sdk\Widget;
use Sdk\Column;
use Sdk\Text;
use Sdk\Button;

class HomePage extends StatelessWidget
{
    public function build(): Widget
    {
        return Column::new([
            Text::new('Mon application'),
            Button::new('Connexion'),
        ]);
    }
}
```

### Avec état (`StatefulWidget`)

```php
<?php

use Sdk\StatefulWidget;
use Sdk\Widget;
use Sdk\Column;
use Sdk\Text;
use Sdk\Button;

class HomePage extends StatefulWidget
{
    private int $count = 0;

    public function build(): Widget
    {
        return Column::new([
            Text::new('Compteur : ' . $this->count),
            Button::new('Incrémenter', onPressed: function () {
                $this->setState(function () {
                    $this->count = $this->count + 1;
                });
            }),
        ]);
    }
}
```

## Widgets disponibles (v0.3)

| PHP | Rôle |
|---|---|
| `Text::new($string)` | texte |
| `Container::new($child, color: $hex = null)` | conteneur à un enfant, couleur de fond optionnelle |
| `Column::new([$children])` | colonne verticale |
| `Row::new([$children])` | ligne horizontale |
| `Button::new($label, onPressed: $closure = null)` | bouton, callback optionnel |
| `SizedBox::new($child = null, width: $w = null, height: $h = null)` | boîte à taille fixe / espacement |

`color` attend une chaîne hexadécimale littérale au format `'#RRGGBB'` (ex. `'#2196F3'`).

## Ce qui est supporté dans le code PHP

- Propriétés d'état typées avec valeur par défaut obligatoire : `private int $count = 0;` (types : `int`, `float`, `string`, `bool`)
- Lecture `$this->propriété`
- Dans un `onPressed` : `$this->setState(function () { ... });`
- Dans un `setState`: assignation `$this->propriété = <expr>;`
- Opérateurs : `.` (concaténation), `+ - * /`

Toute construction PHP hors de ce sous-ensemble (boucles, conditions, autres méthodes, appels de fonctions arbitraires...) fait échouer la transpilation avec une erreur explicite — jamais de génération Dart approximative ou silencieusement fausse.

## Limites actuelles (v0.2)

- Pas de widgets `Image`, `Stack`, `GestureDetector`, `Navigation` (prochaine étape)
- `phpx build apk` / `phpx build ios` pas encore implémentés — pour l'instant, va dans `.flutter/` et utilise directement `flutter build apk` / `flutter build ios` une fois le SDK Android / Xcode installés
- Un seul écran par app pour l'instant (pas de navigation multi-écrans)

## Structure du projet

```
sdk/            classes PHP "widgets" (stubs, servent de contrat/autocomplete — jamais exécutées)
transpiler/     le compilateur PHP -> Dart + le CLI phpx
examples/       apps d'exemple (demo, counter)
```
