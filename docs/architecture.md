# Architecture interne

PhpNitro rend sur un vrai `android.graphics.Canvas` (Skia), pas sur une WebView — même famille d'idée que Flutter (PHP compose un arbre de widgets, un moteur de layout à contraintes le positionne, un moteur de peinture le dessine), mais le "moteur" tourne côté PHP et envoie le résultat déjà calculé au client, plutôt que de tourner dans le process de l'app comme le fait le runtime Dart de Flutter.

## Le cycle : PHP calcule, Kotlin rejoue

1. `NativeRenderPocActivity` (Kotlin) fait une requête `GET /native/layout-demo?screen=...&width=...&height=...` vers le serveur PHP embarqué (`php -S` en dev, un process PHP intégré sur device).
2. `public/index.php` route vers `lib/pages/app/Native*Screen.php::build($screenWidth, $screenHeight)`, qui retourne un arbre de `Widget` (`packages/ui/src/Native/`).
3. Le moteur de layout fait UNE passe descendante : chaque nœud reçoit des `Constraints` (min/max largeur/hauteur, exactement `Flutter`'s `BoxConstraints`) et retourne la `Size` qu'il occupe.
4. Une passe de peinture parcourt le même arbre et append des commandes de dessin plates (`{"type":"rect",...}`, `{"type":"text",...}`) dans un `Canvas`, en coordonnées absolues.
5. `Canvas::toJson()` sérialise `{commands, hitRegions, heroRegions, dismissRegions, contentHeight, ...}` — une seule réponse JSON.
6. `NativeCanvasView.kt` parse cette réponse et rejoue les commandes sur un vrai `Canvas` dans `onDraw()`.

**Chaque interaction refait ce cycle.** Un tap sur un bouton envoie une nouvelle requête HTTP avec `action=...`, PHP recalcule tout l'écran (souvent en quelques millisecondes — `renderTimeMs` est renvoyé dans chaque réponse), et le client rejoue le nouveau jeu de commandes. Pas de diffing d'arbre virtuel côté client, pas d'état PHP qui survit entre deux requêtes (chaque requête est un process PHP indépendant) — tout ce qui doit persister vit dans `$_SESSION`, `Engine\Preferences\Preferences`, ou une vraie base de données.

## Widget : l'unique contrat

```php
interface Widget
{
    public function layout(Constraints $constraints): Size;
    public function paint(Canvas $canvas, float $x, float $y): void;
}
```

Tout widget est une classe qui implémente ça — un `Container`, un `Flex::row([...])`, un `Button`, un écran entier. `layout()` doit être appelé avant `paint()` (le nœud calcule et met en cache sa propre taille) ; c'est ce qui permet à `paint()` de connaître sa propre largeur/hauteur sans la recalculer. Voir [docs/widgets.md](widgets.md) pour le catalogue complet.

## Actions : pas de callback, une chaîne

Un widget interactif (`Button`, `Tappable`, `ListTile`) prend une chaîne `$action`, enregistrée comme "hit region" (rect + action) via `Canvas::hitRegion()`. Au tap, `NativeCanvasView.kt` fait le hit-test côté client (pas de round-trip juste pour savoir "qu'est-ce qui a été touché"), puis appelle `NativeRenderPocActivity.onTap(action, ...)`, qui reconnaît un préfixe connu :

| Préfixe | Effet |
|---|---|
| `navigate:écran` | pousse un nouvel écran sur la pile |
| `back` | dépile |
| `tab:écran` | réinitialise toute la pile sur un écran (barre d'onglets) |
| `toggle:champ` | met à jour `fieldValues[champ]` côté client, re-fetch |
| `device:...` | appelle `NativeDeviceBridge.kt` directement, zéro PHP tant que le résultat n'a pas besoin d'être affiché — voir [docs/device-and-native.md](device-and-native.md) |
| `select:`/`datepicker:`/`timepicker:` | ouvre un vrai dialogue Android |
| n'importe quoi d'autre | envoyé tel quel comme `?action=...` à la prochaine requête — c'est ce que lit `$_GET['action'] ?? null` dans `public/index.php`, et ce à quoi un écran répond en inspectant `$action` avant de construire son arbre |

Exemple minimal (le compteur de `NativeHomeScreen`) :

```php
// Dans public/index.php, avant de construire l'arbre :
if ($action === 'home_increment') {
    Preferences::set('n', (string) ((int) Preferences::get('n', '0') + 1));
}

// Dans l'écran :
new Button('+1', 'home_increment')
```

## Gestes continus : Kotlin ne round-trip pas tout

La plupart des interactions (tap, sélection) valent un aller-retour réseau — elles ne se produisent pas assez souvent pour que la latence se voie. Un glisser (`Dismissible`) est différent : PHP ne voit jamais le geste lui-même, seulement son résultat. `NativeCanvasView.kt` traque le doigt entièrement côté client (translation live des commandes, zéro requête par frame) et n'appelle `onAction` qu'une fois le geste validé au relâchement. Voir [docs/widgets.md#gestes](widgets.md#gestes).

## Animations : deux mécanismes, une seule primitive Kotlin

- **Crossfade d'écran** : à chaque `setCommands()`, l'ancien jeu de commandes et le nouveau sont fondus l'un dans l'autre (`fadeProgress`) — automatique, aucun widget à écrire.
- **FLIP par élément** (`Hero`/`Animated`) : un sous-arbre tagué par une clé stable enregistre son rectangle englobant (`Canvas::beginHero()`) ; si la même clé apparaît à un rectangle différent au rendu suivant, `NativeCanvasView.kt` fait voler ce sous-arbre de l'ancien rectangle au nouveau via une `Matrix`, en interpolant aussi couleur/rayon/bordure de chaque commande individuelle — pas juste la position globale. `Hero` est pensé pour une navigation entre écrans, `Animated` pour un changement local (l'équivalent unifié de `AnimatedContainer`/`AnimatedPositioned`/`AnimatedOpacity` de Flutter).

## Listes longues : fenêtre, pas tout

`LazyList` ne construit/peint que les items dans une fenêtre autour du `scrollY` courant (buffer de 2 hauteurs d'écran de chaque côté), mais annonce la hauteur virtuelle COMPLÈTE (`itemCount * itemHeight`) comme sa propre `Size` — le scroll reste fluide côté client sur toute la plage, et `Canvas::setScrollFollow()` dit au client de re-fetcher en approchant du bord de ce qui est réellement chargé.

## Ce qui reste WebView (legacy, en voie de disparition)

`MainActivity.kt`/`WebAppInterface.kt` existent encore pour héberger d'éventuelles pages HTML — mais `public/index.php` n'a plus AUCUNE route de contenu WebView (`Router`, `Screen`, `PageRenderer`, tous les widgets Tailwind ont été supprimés une fois leur conversion native complète). `NativeRenderPocActivity` est le seul point d'entrée de l'app.

## Backend en process

`lib/backend/` est une pure librairie "façon Symfony" (Controller/Entity/Repository/Service) — pas de point d'entrée HTTP séparé. `public/index.php` délègue toute route `/api/*` à `Backend\Kernel::handle()`, **dans le même process PHP**, en mémoire.

## Base de données

`Engine\Database\Database::connection()` (Doctrine DBAL) — dossier PSR-4 séparé (`packages/database/src/`) qui ne connaît pas la structure de dossiers de l'app qui le consomme : `public/index.php` épingle le chemin SQLite une fois au démarrage via `Database::useSqlitePath()`. La connexion réessaie automatiquement (3 tentatives, backoff) et se reconnecte silencieusement si coupée en cours de session.

## Bundle Android : mêmes chemins, PHP minifié

`bin/phpx bundle:android` mirrore exactement la disposition du dépôt (`public/`, `lib/`, `packages/` au même niveau relatif) dans `android/app/src/main/assets/www/`, pour que le même `composer.json` racine résolve les autoloads identiquement en dev et embarqué. Chaque fichier `.php` passe par un minifieur `token_get_all()` au passage (voir [docs/cli.md#minification-pas-obfuscation](cli.md#minification-pas-obfuscation)). Les répertoires de packages sont découverts via `glob('packages/*/src')`, pas une liste codée en dur.
