# Proposition : éditeur visuel drag & drop

Statut : **idée, pas implémentée**. Ce document répond à la question "est-ce possible ?" posée en session — il pose le problème, compare aux précédents existants (Android XML, Flutter/FlutterFlow), et propose une portée réaliste si on décide de le construire. Rien ici n'engage à le faire.

## Le problème de fond

Un layout Android XML est du **markup déclaratif** — un fichier texte qui décrit une arborescence de vues sans aucune logique. Android Studio peut le parser, le rendre visuellement, le régénérer depuis un éditeur graphique, et reparser ce qu'un humain a modifié à la main : c'est bidirectionnel parce que le format est simple et sans ambiguïté.

`Screen::build()` dans PhpNitro n'est **pas** du markup — c'est une méthode PHP qui **retourne** un arbre de widgets, mais qui peut contenir n'importe quoi d'autre : boucles, conditions, appels de méthode, accès à `$this->state`. Flutter a exactement le même problème (le `build()` d'un `StatelessWidget` est du code Dart, pas du XML) — et c'est pour ça que **Flutter lui-même n'a jamais sorti d'éditeur visuel officiel**. FlutterFlow (produit tiers, payant) contourne le problème en ne touchant jamais au code Dart existant : il maintient son **propre modèle visuel séparé** (stocké dans son propre format, pas du Dart), génère du Dart à sens unique depuis ce modèle, et n'essaie jamais de reparser du Dart écrit à la main pour le refaire correspondre au modèle. Autrement dit : ce n'est pas un éditeur XML-like bidirectionnel, c'est un générateur de code à sens unique avec son propre fichier de projet.

## Trois approches possibles, par ordre de risque croissant

### A. Générateur à sens unique (le plus réaliste)

Un éditeur visuel séparé qui maintient son propre format (JSON, par exemple), avec un bouton "Générer le code PHP". Le PHP généré est un point de départ à éditer ensuite à la main — pas un fichier qu'on rouvre dans l'éditeur visuel après modification manuelle (comme FlutterFlow).

- **Avantage** : aucune ambiguïté de parsing, l'éditeur ne lit jamais de PHP, juste son propre JSON.
- **Inconvénient** : dès qu'un développeur touche au PHP généré (pour ajouter une condition, une boucle, une action), le fichier "sort" de l'éditeur visuel définitivement — pas de va-et-vient.
- Effort estimé : quelques semaines (interface drag & drop + palette des ~75 widgets existants + panneau de propriétés basé sur leurs params typés + générateur JSON → PHP).

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

Commencer par **A** (générateur à sens unique) — c'est la seule option qui ne demande ni parser PHP fragile ni changement de philosophie du framework, et elle valide déjà l'essentiel : palette de widgets, panneau de propriétés typées (`Color`, `Rounded`, `TextSize`...), prévisualisation live. Si l'usage réel montre que le round-trip (B) manque cruellement, on aura au moins un éditeur fonctionnel comme base plutôt que de partir directement sur le chantier le plus incertain.

## Ce que ça demanderait concrètement (option A)

1. Une palette listant les widgets ayant un constructeur "simple" (params scalaires + enfants `Widget`) — la référence API générée (`phpx docs:api`, voir `docs/api/`) donne déjà cette liste avec les signatures exactes, réutilisable directement comme source de vérité pour la palette.
2. Un canvas de rendu live — probablement en rendant le PHP généré côté serveur à chaque changement (le framework fait déjà du rendu serveur, pas besoin de réimplémenter le rendu des widgets en JS).
3. Un panneau de propriétés qui expose les params typés existants (`Color`, `Rounded`, `TextSize`, `FontWeight`) comme des contrôles (color picker, select) plutôt que des champs texte libres.
4. Un générateur JSON → PHP, avec un bouton export explicite (pas une sauvegarde automatique qui écrase un fichier édité à la main).
