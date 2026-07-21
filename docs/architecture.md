# Architecture interne

## Rendu : PHP décide, JS ne fait que swapper

`Engine\PageRenderer` émet soit un document HTML complet, soit — pour une requête interceptée par `nav.js` (header `X-Phpx-Partial: 1`, détecté par `Engine\Navigation::isPartial()`) — un petit JSON `{html, path, theme, showBottomNav}` contenant juste l'arbre de widgets rendu. Le serveur ne devine jamais côté client : il rend exactement le même arbre PHP dans les deux cas, seul l'emballage diffère.

La navigation SPA (`assets/js/nav.js`) intercepte les clics sur les liens et les soumissions de formulaire du même domaine, fait la requête avec ce header, et remplace `#phpx-content` par la réponse — `history.pushState` tient l'URL à jour. Le bandeau de navigation persistant (`#phpx-bottom-nav-wrapper`) est rendu **une seule fois** par requête complète et n'est jamais recréé lors d'un swap partiel — seule sa visibilité (`hidden`) et l'onglet actif changent.

Une vraie navigation (changement de route) enveloppe le contenu injecté dans un `<div class="phpx-page-enter">` frais, dont le keyframe CSS (opacity seule, volontairement sans `transform` — voir plus bas) joue automatiquement à l'insertion. Une action qui reste sur la même route (ex. incrémenter un compteur) ne l'enveloppe pas, pour éviter un flash visuel à chaque clic.

**Piège CSS évité** : un premier essai animait aussi `transform`, ce qui fait de l'élément animé un nouveau "containing block" pour tout descendant `position: fixed` — cassant le `FloatingActionButton` après toute navigation réelle. D'où l'opacity-only pour cette transition précise.

## Screen : état, actions, routes

`Screen` (abstrait) porte l'état persisté en session (`$this->state`, initialisé par `initialState()`), les paramètres de route (`$this->params`), et `build(): Widget`. Une action nommée (`Button::make($label, action: 'foo')`) appelle `onFoo(array $data = [])` sur la même classe ; un retour `string` redirige, `null` reste sur la page. Chaque combinaison classe+paramètres de route a son propre état de session (deux visites de `/product/1` et `/product/2` ne partagent rien).

`Engine\Router` résout `path => ScreenClass` avec capture de segments (`/product/{id}`), déclaré une fois dans `public/index.php`.

## Backend en process

`lib/backend/` est une pure librairie — pas de `public/` à elle, pas de point d'entrée HTTP séparé. `public/index.php` délègue toute route `/api/*` à `Backend\Kernel::handle()`, **dans le même processus PHP**, en mémoire. Zéro configuration supplémentaire, y compris sur Android : le backend est toujours disponible, implicitement.

## Sécurité des actions

`Engine\Csrf` génère un jeton stable par session (`Csrf::token()`, jamais rotaté), vérifié globalement sur tout POST. `Engine\Navigation` distingue requête complète vs partielle.

## Base de données

`Engine\Database\Database::connection()` (Doctrine DBAL) — un dossier PSR-4 séparé (`packages/database/src/`), qui ne connaît pas la structure de dossiers de l'app qui le consomme : `public/index.php` épingle le chemin SQLite une fois au démarrage via `Database::useSqlitePath()`, avant toute route. La connexion réessaie automatiquement (3 tentatives, avec backoff) et se reconnecte silencieusement si coupée en cours de session.

## Bundle Android : mêmes chemins, PHP minifié

`bin/phpx bundle:android` mirrore exactement la disposition du dépôt (`public/`, `lib/`, `packages/` au même niveau relatif) dans `android/app/src/main/assets/www/`, pour que le même `composer.json` racine résolve les autoloads identiquement en dev et embarqué — pas de réécriture de chemin, pas de symlink Composer à aplatir. Chaque fichier `.php` (sauf `vendor/`, composer-installé frais dans le bundle) passe par un minifieur `token_get_all()` au passage (voir [docs/cli.md#minification-pas-obfuscation](cli.md#minification-pas-obfuscation)).

Les répertoires de packages sont découverts via `glob('packages/*/src')`, pas une liste codée en dur — un nouveau package ajouté au dossier `packages/` est automatiquement inclus dans le bundle sans toucher à `bin/phpx`.

## Pont natif Android/iOS

`assets/js/device.js`/`dialogs.js` détectent `window.AndroidNative || window.iOSNative` et préfèrent toujours le pont natif présent, ne retombant sur les Web APIs standard que si aucun des deux n'est disponible (navigateur, tests locaux). Un seul point d'entrée JS par capacité (`window.phpxDevice.vibrate(...)`, etc.), jamais de branche spécifique à une plateforme dans le code widget/service PHP — la parité de noms de méthodes entre `WebAppInterface.kt` et `WebAppInterface.swift` est ce qui rend ça possible.
