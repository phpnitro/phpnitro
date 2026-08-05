# Proposition : éditeur visuel drag & drop

Statut : **idée, pas implémentée**. Ce document répond à la question "est-ce possible ?" posée en session — il pose le problème, compare aux précédents existants (Android XML, Flutter/FlutterFlow), et propose une portée réaliste si on décide de le construire. Rien ici n'engage à le faire.

## Le problème de fond

Un layout Android XML est du **markup déclaratif** — un fichier texte qui décrit une arborescence de vues sans aucune logique. Android Studio peut le parser, le rendre visuellement, le régénérer depuis un éditeur graphique, et reparser ce qu'un humain a modifié à la main : c'est bidirectionnel parce que le format est simple et sans ambiguïté.

`Screen::build()` dans PhpNitro n'est **pas** du markup — c'est une méthode PHP qui **retourne** un arbre de widgets, mais qui peut contenir n'importe quoi d'autre : boucles, conditions, appels de méthode, accès à `$this->state`. Flutter a exactement le même problème (le `build()` d'un `StatelessWidget` est du code Dart, pas du XML) — et c'est pour ça que **Flutter lui-même n'a jamais sorti d'éditeur visuel officiel**. FlutterFlow (produit tiers, payant) contourne le problème en ne touchant jamais au code Dart existant : il maintient son **propre modèle visuel séparé** (stocké dans son propre format, pas du Dart), génère du Dart à sens unique depuis ce modèle, et n'essaie jamais de reparser du Dart écrit à la main pour le refaire correspondre au modèle. Autrement dit : ce n'est pas un éditeur XML-like bidirectionnel, c'est un générateur de code à sens unique avec son propre fichier de projet.

## Trois approches possibles, par ordre de risque croissant

### A. Générateur à sens unique (le plus réaliste)

Un éditeur visuel séparé qui maintient son propre format (JSON, par exemple), avec un bouton "Générer le code PHP". Le PHP généré est un point de départ à éditer ensuite à la main — pas un fichier qu'on rouvre dans l'éditeur visuel après modification manuelle (comme FlutterFlow).

- **Avantage** : aucune ambiguïté de parsing, l'éditeur ne lit jamais de PHP, juste son propre JSON.
- **Inconvénient** : dès qu'un développeur touche au PHP généré (pour ajouter une condition, une boucle, une action), le fichier "sort" de l'éditeur visuel définitivement — pas de va-et-vient. **Résolu par A′ ci-dessous.**
- Effort estimé : quelques semaines (interface drag & drop + palette des ~75 widgets existants + panneau de propriétés basé sur leurs params typés + générateur JSON → PHP).

### A′. Scission génération/logique, façon WinForms/VB (variante recommandée de A)

Le point faible de A ci-dessus (aller simple, plus de retour possible dès qu'on touche au fichier) a un précédent qui le résout complètement, éprouvé depuis des décennies : **WinForms** génère un fichier `Form1.Designer.cs` que le développeur ne touche JAMAIS à la main, et un fichier `Form1.cs` (le "code-behind") qui est à lui seul — le designer régénère le premier sans jamais avoir à lire ou comprendre le second. Un double-clic sur un bouton dans le designer crée un stub de handler VIDE dans le fichier À LA MAIN, jamais dans le fichier généré. Pas de parsing de code arbitraire nulle part : chaque fichier a un seul propriétaire, donc aucune ambiguïté à résoudre.

Appliqué à PhpNitro, deux fichiers au lieu d'un :

- **`lib/pages/generated/ProfilScreenDesign.php`** — entièrement régénérable, jamais modifié à la main (même convention que "ne jamais éditer les fichiers dans `docs/api/`, ils seraient écrasés au prochain `phpx docs:api`", déjà établie dans ce framework). Contient l'arbre visuel statique : `new Container(new Padding(...), Flex::column([...]))`.
- **`lib/pages/NativeProfilScreen.php`** — à la main, jamais écrasé, enregistré via `Engine\Native\Router`. Son `build()` appelle `ProfilScreenDesign::build(...)` et pose la vraie logique par-dessus (lire `$_SESSION`/`Store`, conditions, boucles sur des données réelles).
- **Le double-clic** (assigner une action à un widget dans l'éditeur) scaffolde un stub `if ($action === 'save_profile') { /* TODO */ }` dans `public/index.php` **une seule fois, jamais s'il existe déjà** — exactement la garde que `phpx make:page` applique déjà pour ne jamais écraser un contrôleur existant.
- **Une page dynamique n'empêche PAS le drag & drop** : on dessine visuellement la FORME d'un élément une fois (une carte produit, une ligne de liste) dans le fichier généré ; c'est le fichier à la main qui décide, en PHP normal, combien de fois l'appeler et avec quelles données (`foreach ($produits as $p) { ProfilScreenDesign::carteProduit($p->nom, $p->prix); }`) — le générateur n'a jamais besoin de comprendre la boucle, juste de produire la pièce visuelle réutilisable.

- **Avantage** : règle le vrai reproche fait à A (aller simple) sans le risque de parsing fragile de B — l'éditeur visuel reste TOUJOURS utilisable pour retoucher le visuel d'un écran, même après des mois de développement de sa logique, parce qu'il ne lit jamais que le fichier généré.
- **Inconvénient** : une convention à respecter (ne jamais éditer le dossier `generated/` à la main) plutôt qu'une contrainte techniquement imposée — comme WinForms, rien n'empêche physiquement un développeur de casser la règle, seule la discipline (et un commentaire d'avertissement en tête de fichier, comme `docs/api/*.md` déjà) la fait tenir.
- Effort estimé : proche de A (même palette, même preview, même génération) + la convention de scission à documenter clairement et à faire respecter par `phpx make:screen` (ou équivalent) dès la création.

### B. Sous-ensemble PHP restreint, reparsable (round-trip partiel)

Restreindre ce que l'éditeur visuel accepte de reparser à un **sous-ensemble syntaxique précis** : uniquement un `return` unique contenant une chaîne d'appels `Widget::make(...)` avec des arguments littéraux (chaînes, nombres, booléens, autres `Widget::make()` imbriqués) — jamais de variable, boucle, condition, ou accès à `$this->state` dans l'arbre visuel lui-même.

- Utiliserait `nikic/php-parser` (déjà une dépendance indirecte via l'écosystème PHP, pas dans ce monorepo actuellement) pour parser le fichier réel, détecter si son `build()` respecte le sous-ensemble, et si oui l'afficher dans l'éditeur visuel ; sinon, afficher un message "ce fichier a été modifié manuellement, édition visuelle désactivée pour cet écran".
- **Avantage** : un vrai round-trip pour les écrans qui restent simples (formulaires basiques, pages de contenu statique).
- **Inconvénient** : dès qu'un écran a la moindre logique réelle (ce qui arrive vite — un simple `if ($this->state['error'])` le sort du sous-ensemble), l'édition visuelle se désactive. Risque de créer une fausse impression de "no-code" qui se brise sur le premier écran un peu réel.
- Effort estimé : plusieurs semaines à quelques mois (le parser + editeur + la détection fiable du sous-ensemble sont chacun un vrai chantier).

### C. Nouveau langage de layout déclaratif (le plus risqué)

Introduire un format `.phpx.yml`/`.phpx.json` optionnel pour la partie **statique** de l'arbre (ce qu'Android fait avec XML), compilé en PHP au build. La logique dynamique (actions, état) resterait toujours dans une classe `Screen` PHP normale, référencée depuis le layout déclaratif.

- **Avantage** : un vrai format markup, donc un vrai éditeur bidirectionnel possible pour de bon — même mécanique qu'Android Studio.
- **Inconvénient** : introduit un DEUXIÈME modèle mental à apprendre (code PHP pour la logique + markup déclaratif pour le layout) — va à l'encontre de la promesse actuelle de ce framework ("si tu sais écrire du PHP, tu sais écrire une app"). Change la nature du framework, pas juste un ajout d'outil.
- Effort estimé : mois, et une vraie décision de design à assumer (pas juste une feature, un changement de philosophie).

## Recommandation si on décide d'avancer

Commencer par **A′** (scission génération/logique façon WinForms) plutôt que A pur — même effort, même absence de parser PHP fragile, mais sans l'inconvénient de l'aller simple : l'éditeur visuel reste utilisable indéfiniment sur la partie qu'il possède. Ça valide déjà l'essentiel : palette de widgets, panneau de propriétés typées (`Color`, `Rounded`, `TextSize`...), prévisualisation live. Si l'usage réel montre que même la scission A′ ne suffit pas (un besoin réel de réimporter une modification manuelle du fichier généré), le round-trip complet (B) reste une option de repli, pas un point de départ.

## Ce que ça demanderait concrètement (option A′)

1. Une palette listant les widgets ayant un constructeur "simple" (params scalaires + enfants `Widget`) — la référence API générée (`phpx docs:api`, voir `docs/api/`) donne déjà cette liste avec les signatures exactes, réutilisable directement comme source de vérité pour la palette. **Corrigé depuis** : le générateur ne voyait jusqu'ici QUE les classes directement à la racine de `packages/*/src/` — tout `Engine\Native\*` (les ~85 vrais widgets, tous logés dans un sous-dossier `Native/`) en était absent, 1 classe documentée au lieu de 85. `phpx docs:api` recurse maintenant dans les sous-espaces de noms ; la palette a une vraie source de vérité complète pour la première fois.
2. Un canvas de rendu live — voir la section suivante, ce point a complètement changé depuis l'écriture initiale de ce document.
3. Un panneau de propriétés qui expose les params typés existants (`Color`, `Rounded`, `TextSize`, `FontWeight`) comme des contrôles (color picker, select) plutôt que des champs texte libres.
4. Un générateur JSON → PHP qui écrit UNIQUEMENT dans `lib/pages/generated/*.php` (jamais dans le fichier à la main) — peut se sauvegarder automatiquement à chaque changement, justement parce que ce fichier n'est jamais censé contenir de travail manuel à perdre. À la toute première génération d'un écran, crée aussi le fichier à la main (`lib/pages/NativeXScreen.php`, avec son `Router::register()`) — mais seulement s'il n'existe pas déjà, jamais en écrasant.

## Mise à jour : le moteur de rendu natif change le point 2

Ce document a été écrit avant (ou sans tenir compte de) la bascule complète vers le rendu natif (`docs/architecture.md`) — à l'époque, "canvas de rendu live" voulait dire "rendre le PHP en HTML côté serveur", une approximation visuelle du rendu réel. Ce n'est plus le cas : PHP émet maintenant un JSON de commandes de dessin plates (`{"type":"rect",...}`, `{"type":"text",...}`) que `NativeCanvasView.kt` rejoue tel quel contre un vrai `Canvas` Android — voir "Le cycle" dans `docs/architecture.md`. Ce protocole JSON est le vrai levier pour cette proposition :

- **Le preview peut être pixel-fidèle au rendu mobile réel**, pas une approximation HTML. Une petite bibliothèque JS qui rejoue ce même JSON contre un `<canvas>` HTML5 (un port direct — pas une réécriture — des `drawXxxCommand()` de `NativeCanvasView.kt` : rect, text, icon, circle, arc, image, clientPanel...) donne un rendu qui utilise LE MÊME chemin d'exécution PHP que l'app mobile, pas un deuxième moteur de rendu à maintenir en parallèle et à faire dériver.
- **Boucle concrète** : l'éditeur garde son propre arbre JSON (jamais du PHP tant qu'on n'exporte pas). À chaque modification, il écrit directement dans `lib/pages/generated/XScreenDesign.php` (voir A′ plus bas — ce fichier PEUT se sauvegarder à chaque frappe puisqu'il n'est jamais censé contenir de travail manuel), le pousse vers `phpx serve` déjà lancé (`phpx dev:push`, qui existe déjà pour le hot-reload de l'app mobile), interroge `/native/layout-demo?screen=...` pour récupérer le JSON réel, et le dessine sur le canvas JS. Il n'y a alors plus de "bouton Générer" séparé du preview — le fichier généré EST déjà à jour à chaque frappe, seul le fichier à la main (jamais touché par l'éditeur) reste une étape consciente du développeur.
- **Le nouvel `Engine\Native\Router`** (voir `docs/architecture.md#routes-à-paramètres`) ferme une autre boucle : un écran généré par l'éditeur peut s'enregistrer lui-même via `Router::register()` sans toucher au `match($screen)` historique — "créer un écran depuis l'éditeur" peut rester une opération strictement additive (un nouveau fichier + un `Router::register()`), jamais une édition manuelle d'un fichier central que deux outils pourraient se disputer.

Ça ne change rien aux options B/C — ça rend A′ sensiblement plus solide qu'au moment de l'écriture initiale de ce document (qui ne connaissait que le A pur, sans scission) : le preview n'est plus une approximation HTML, il est fidèle au pixel près, et la scission génération/logique règle le seul vrai reproche qui restait à faire à cette famille d'approches.
