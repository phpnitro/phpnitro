# Tutoriel : construire une fonctionnalité complète (liste de tâches)

> **Périmé** : ce tutoriel décrit une architecture antérieure (classes `Screen`, `Engine\Router` par chemins HTTP, formulaires POST classiques) qui n'existe plus dans le framework actuel — vérifié : aucune de ces classes n'existe dans `packages/ui/src/`. Le modèle réel (écrans = classes avec `build(float, float): Widget`, actions = chaînes préfixées reconnues côté client, `?screen=`/`Engine\Native\Router`) est documenté dans [docs/getting-started.md](../getting-started.md) et [docs/architecture.md](../architecture.md). Ce fichier n'est réécrit pour l'instant — pas encore fait, gardé ici pour référence historique seulement, ne pas suivre ses exemples tels quels.

Ce tutoriel construit une petite liste de tâches — ajout, complétion, suppression — pour couvrir en une fois tout le modèle mental de PhpNitro : état, actions, formulaires, redirection. Suppose un projet déjà créé (`phpx new`, voir [démarrage rapide](../getting-started.md)).

## 1. Créer la page

```bash
bin/phpx make:page Todos /todos
```

Ça génère `lib/pages/app/TodosPage.php` et enregistre la route `/todos` dans `public/index.php`.

## 2. Déclarer l'état initial

Chaque écran a son propre état, sérialisé en session — pas de base de données nécessaire pour ce tutoriel (voir la section 6 pour persister réellement).

```php
<?php

namespace Engine\App;

use Engine\Button;
use Engine\Column;
use Engine\Row;
use Engine\Screen;
use Engine\Text;
use Engine\TextField;
use Engine\Widget;

final class TodosPage extends Screen
{
    protected function initialState(): array
    {
        return ['items' => []];
    }

    public function build(): Widget
    {
        return Column::make([
            Text::make('Mes tâches', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),
        ], 'flex flex-col gap-4 p-4');
    }
}
```

## 3. Ajouter une tâche (formulaire + action)

Un `Form` regroupe des champs sous une action nommée ; l'écran reçoit toutes les valeurs dans `onXxx(array $data)`.

```php
protected function onAddTodo(array $data): void
{
    $label = trim($data['label'] ?? '');
    if ($label === '') {
        return; // validation minimale — rien à ajouter
    }

    $this->state['items'][] = ['label' => $label, 'done' => false];
}
```

```php
use Engine\Form;

// dans build() :
Form::make([
    TextField::make('label', label: 'Nouvelle tâche'),
    Button::make('Ajouter'),
], action: 'addTodo'),
```

Le formulaire soumet en POST, `onAddTodo()` s'exécute, l'état est sauvegardé en session, puis PHP redirige (POST-redirect-GET — pas de resoumission au rechargement).

## 4. Afficher la liste et cocher une tâche

```php
protected function onToggleTodo(array $data): void
{
    $index = (int) ($data['index'] ?? -1);
    if (isset($this->state['items'][$index])) {
        $this->state['items'][$index]['done'] = !$this->state['items'][$index]['done'];
    }
}
```

`Checkbox` (comme tous les champs de `TextField`/`SelectBox`) est un simple champ de formulaire — il n'a pas de paramètre pour déclencher une action tout seul, il attend d'être soumis dans un `Form`. Pour un bouton qui déclenche une action avec un paramètre dynamique (ici l'index de la ligne), `Button::make(..., onClick: ...)` accepte n'importe quelle chaîne JS, y compris un appel direct à `phpxNav.submitAction()` avec des champs supplémentaires :

```php
// dans build(), une ligne par tâche :
Row::make([
    Button::make(
        $item['done'] ? '☑' : '☐',
        onClick: "phpxNav.submitAction('toggleTodo', {index: {$i}})",
        classes: 'text-xl',
    ),
    Text::make($item['label'], $item['done'] ? 'line-through text-gray-400' : ''),
], 'flex items-center gap-2'),
```

C'est le même principe que les services device (`Button::make($label, onClick: Torch::onClick())`, voir le tutoriel suivant) : n'importe quel élément peut déclencher n'importe quelle action, avec des données arbitraires — pas besoin d'un widget dédié par cas d'usage.

## 5. Supprimer une tâche

```php
protected function onRemoveTodo(array $data): void
{
    $index = (int) ($data['index'] ?? -1);
    unset($this->state['items'][$index]);
    $this->state['items'] = array_values($this->state['items']); // ré-indexe
}
```

```php
Button::make('Retirer', onClick: "phpxNav.submitAction('removeTodo', {index: {$i}})", classes: 'text-red-600 text-sm'),
```

## 6. Persister réellement (au-delà de la session)

L'état d'un `Screen` vit en session — il disparaît si le serveur PHP redémarre (sur Android : si l'app est tuée). Pour une vraie persistance, deux options déjà dans le framework :

- **`Engine\Preferences\Preferences`** (clé-valeur, backé SQLite) pour des données simples qui doivent survivre un redémarrage de l'app.
- **Une vraie entité** (`bin/phpx make:entity Todo`) + `Engine\Database\Database::connection()` pour une vraie table, des requêtes, des relations — voir [Architecture interne](../architecture.md#base-de-données).

## Ce que ce tutoriel a couvert

`initialState()` → `Form`/`onXxx(array $data)` → mutation de `$this->state` → redirection automatique. C'est tout le modèle de PhpNitro — la même boucle vaut pour n'importe quelle fonctionnalité, panier e-commerce compris (voir [`examples/ecom`](../../examples/ecom/README.md)).
