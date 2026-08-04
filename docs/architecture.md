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

## État client : quand ne PAS aller chercher PHP

Par défaut, toute interaction déclenche un aller-retour réseau — c'est le modèle par défaut de ce framework, et il est délibérément simple (PHP décide de tout, Kotlin rejoue). Mais certaines interactions n'ont **aucune raison** de toucher le serveur : quel onglet est sélectionné, si un panneau est ouvert ou fermé, où en est un scroll imbriqué. Le contenu des deux états a déjà été envoyé dans la même réponse — rien de nouveau à demander à PHP.

Le mécanisme générique derrière ça (introduit par `ClientTabs`, réutilisé tel quel par `BottomSheet` et `HorizontalScroll`) :

1. **`Canvas::clientTabPanel($key, $index, $initiallyActive, ...)`** — chaque état possible (chaque onglet, ou "ouvert"/"fermé" pour un panneau) est peint dans son propre `Canvas` imbriqué et envoyé comme une entrée `{type: "clientPanel", key, index, commands, hitRegions}`. `$initiallyActive` marque laquelle est l'état de départ.
2. Côté Kotlin, `NativeCanvasView.kt` garde une map locale `clé -> index sélectionné`, **amorcée une seule fois** depuis `initiallyActive` — un re-rendu ultérieur du même écran ne l'écrase jamais (sinon un onglet déjà changé par l'utilisateur reviendrait à sa valeur de départ à chaque refetch).
3. Basculer d'un état à l'autre est une simple action `"clientTab:{clé}:{index}"` — `NativeRenderPocActivity`'s la reconnaît déjà génériquement, aucun nouveau dispatch à écrire.

**Pour construire un nouveau widget sur ce modèle** (un accordéon, un carrousel de pages, n'importe quoi avec un état "lequel est actif" purement visuel) : peindre chaque état possible dans son propre `Canvas` imbriqué, appeler `clientTabPanel()` une fois par état avec la même clé, et déclencher les transitions via des `Tappable` dont l'action est `"clientTab:{clé}:{index}"`. Voir `BottomSheet.php` (`packages/ui/src/Native/BottomSheet.php`) pour un exemple concret différent de "plusieurs panneaux côte à côte" — un seul panneau, ouvert (`index=1`) ou fermé (`index=0`).

**Limite connue** : ce mécanisme ne fait QUE basculer instantanément entre des rendus déjà envoyés — pas d'interpolation/animation de transition entre les deux (`BottomSheet` s'ouvre/se ferme sans glissement), et aucune des deux versions ne peut dépendre d'un calcul PHP qui n'a pas encore eu lieu. Pour une vraie transition animée, voir `Hero`/`Animated` (section suivante) ; pour un état qui doit vraiment redemander PHP, c'est le modèle par défaut (round-trip) qui s'applique, pas celui-ci.

## Animations : deux mécanismes, une seule primitive Kotlin

- **Crossfade d'écran** : à chaque `setCommands()`, l'ancien jeu de commandes et le nouveau sont fondus l'un dans l'autre (`fadeProgress`) — automatique, aucun widget à écrire.
- **FLIP par élément** (`Hero`/`Animated`) : un sous-arbre tagué par une clé stable enregistre son rectangle englobant (`Canvas::beginHero()`) ; si la même clé apparaît à un rectangle différent au rendu suivant, `NativeCanvasView.kt` fait voler ce sous-arbre de l'ancien rectangle au nouveau via une `Matrix`, en interpolant aussi couleur/rayon/bordure de chaque commande individuelle — pas juste la position globale. `Hero` est pensé pour une navigation entre écrans, `Animated` pour un changement local (l'équivalent unifié de `AnimatedContainer`/`AnimatedPositioned`/`AnimatedOpacity` de Flutter).

## Outils de développement : perf overlay + inspecteur de widgets

Uniquement construits quand `isDebuggable()` — aucun coût, aucun code, dans un build release. Un badge `🛠` (bas-droite) bascule un panneau texte monospace : temps d'aller-retour réseau, temps de rendu PHP (`renderTimeMs`), nombre de commandes/hit regions, si le dernier fetch a été sauté (`Canvas::stableHash()` inchangé) et si le dernier redraw était partiel (dirty-rect) ou complet — les mêmes chiffres qu'avant, mais visibles sur l'écran plutôt que seulement dans logcat.

Un second badge `🔍` bascule un mode inspecteur — pas un vrai arbre de widgets à parcourir (rien ne survit côté serveur après `paint()`, voir "Le cycle" plus haut), mais le prochain tap est intercepté avant dispatch : au lieu de déclencher l'action, une boîte de dialogue affiche l'`action` exacte et les bornes (dp) de la hit region tapée — répond à "pourquoi cet élément n'est pas tappable" ou "quelle action ce bouton envoie réellement" sans ajouter de protocole réseau.

## Routes à paramètres (un ou plusieurs)

Un token d'écran (ce que `screenStack` empile) a la forme `"nom"` ou `"nom?clé=valeur&clé2=valeur2"` — une vraie query string, pas des segments positionnels. `navigate:product?id=42&tab=reviews` pousse exactement ce token ; `fetchDrawCommands()` le sépare une seule fois (`nom` avant le premier `?`, le reste tel quel), ré-encode chaque paire individuellement (une valeur contenant `&`/`=`/de l'unicode ne casse rien) et l'ajoute à la requête HTTP — côté PHP, `$_GET['id']`/`$_GET['tab']` fonctionnent exactement comme pour n'importe quel autre paramètre de requête, aucune convention spéciale à connaître. Voir `NativeProductScreen::build()` pour l'exemple concret (`$id` obligatoire, `$tab` optionnel).

Seule règle : un nom de paramètre de route ne doit pas entrer en collision avec les clés déjà réservées par le moteur lui-même (`screen`, `width`, `height`, `action`, `online`, `dark`, `locale`, `scrollY`, `fields`, `lastHash`) — un nom de champ de formulaire (`fieldsParam`) ni un `id`/`tab` arbitraire n'y touchent normalement, mais c'est la seule contrainte.

## Deep links

`phpnitro://product?id=42&tab=reviews` (n'importe quel host + path, sauf `oauth-callback` réservé au retour OAuth) arrive dans `NativeRenderPocActivity` via le même mécanisme que le callback OAuth (`android:launchMode="singleTask"`, `AndroidManifest.xml`, `onNewIntent()`) : `deepLinkScreenToken()` reconstruit le même token `"product?id=42&tab=reviews"` que `navigate:` produirait — `uri.query` fournit directement la partie paramètres (décodée une fois ici, ré-encodée une fois dans `fetchDrawCommands()`, comme n'importe quelle autre route), pas de segments de chemin positionnels à mapper. Rien à réapprendre côté PHP, un deep link est juste une autre façon d'arriver sur un écran ordinaire. À froid (l'app n'était pas lancée), c'est `onCreate()` qui le lit ; à chaud, `onNewIntent()`. `MainActivity` (WebView, legacy) a le même mécanisme indépendamment via `deepLinkPath()` — les deux Activities gèrent le même schéma `phpnitro://` chacune à sa façon, pas de code partagé entre elles.

## Transitions de navigation

`NativeCanvasView`'s crossfade (ci-dessus) est un simple fondu d'opacité par défaut. `Canvas::setTransition(string $type)` (`'fade'` | `'slideLeft'` | `'slideRight'` | `'slideUp'`) ajoute un décalage horizontal/vertical calculé sur exactement le même `fadeProgress` — pas de nouvelle horloge côté client. `public/index.php` en pose un par défaut selon la forme de l'action (`navigate:` → `slideLeft`, `back` → `slideRight`, un `tab:` garde le fondu simple, c'est un déplacement latéral pas un push/pop de pile) ; n'importe quel écran peut appeler `setTransition()` lui-même après pour l'écraser. Comme `renderTimeMs`, ce champ est exclu de `stableHash()` — purement présentationnel, et une vraie navigation ne passe de toute façon jamais par le raccourci "hash inchangé".

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
