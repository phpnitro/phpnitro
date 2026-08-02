# Moteur de rendu natif — architecture et plan de route

Branche : `feature/native-render-engine`. Statut : phases 0-5 et 7 (amorcée) faites et vérifiées sur device réel — layout à contraintes, hit-testing, animations Choreographer, 2235 icônes Material réelles, dégradés, défilement, images réseau, navigation multi-écrans, un écran natif branché sur de vraies données (`Engine\Preferences\`). Phase 6 (iOS) explicitement mise de côté pour l'instant.

## Objectif

Remplacer le rendu HTML/CSS dans une WebView par un rendu natif direct (Android `Canvas`, iOS Core Graphics), tout en gardant PHP comme "cerveau" — état, logique, arbre de widgets, layout. C'est le changement architectural discuté en session : sortir de la WebView pour de vrai, pas juste pousser le `<canvas>` existant plus loin.

## Pourquoi ce n'est PAS "écrire notre propre Skia"

`android.graphics.Canvas` est **déjà backé par Skia** au niveau de l'OS Android — c'est le même moteur que Chromium/Flutter utilisent, juste exposé directement par la plateforme au lieu de passer par un navigateur. Idem côté iOS avec Core Graphics/Metal. Donc le vrai travail n'est pas d'écrire un moteur de rendu 2D — c'est :

1. **Un moteur de layout côté PHP** (un Canvas natif brut n'a aucune notion de flexbox — contrairement à CSS, il faut calculer nous-mêmes position/taille de chaque élément).
2. **Un protocole de commandes de dessin** sérialisable (JSON pour commencer) que PHP émet et que le natif rejoue tel quel contre le vrai Canvas.
3. **Un pont natif fin** qui interprète ces commandes — beaucoup plus petit que `WebAppInterface.kt` actuel, qui fait tourner une WebView entière.

## Ce que ça coûte, honnêtement

- Layout : reproduire (au moins) un flexbox à un axe (Column/Row) en PHP pur — mesure + placement, comme le fait Flutter en interne (juste sans le moteur C++ optimisé).
- Mesure de texte : le point le plus dur en pratique — PHP doit connaître la largeur qu'un texte va occuper AVANT que le natif le dessine, pour calculer le layout. Pas de solution magique ; voir phase 4.
- Interaction : plus de DOM, plus d'`onclick` — il faut un hit-test manuel (comparer les coordonnées du tap à l'arbre de rectangles calculé) pour retrouver quel widget a été touché et déclencher la bonne action.
- Deux implémentations natives séparées à terme (Android + iOS), pas une seule comme le HTML aujourd'hui.
- "Plus rapide que Flutter" n'est pas acquis d'office : Flutter compile Dart en AOT et tourne tout en process ; ce framework garde un aller-retour PHP à chaque interaction (le même modèle "serveur" que le reste du framework). Le vrai levier de vitesse ne sera pas "PHP est plus rapide que Dart" (faux), mais : garder le process PHP chaud (déjà le cas), sérialiser en binaire plutôt qu'en JSON à terme, et surtout ne JAMAIS faire d'aller-retour PHP pour une animation en cours — l'interpolation doit se jouer nativement une fois l'arbre de commandes reçu, pilotée par `Choreographer` (Android) — pas par des requêtes répétées.

## Plan de route par phases (chacune vérifiable indépendamment)

- **Phase 0 (démarrée maintenant)** : preuve de concept — une `View` Android custom, une liste de commandes codée en dur (un rectangle arrondi + un texte), rendue sur le device réel. Objectif unique : prouver que le tuyau PHP → natif → vrai Canvas fonctionne, avant d'investir dans le reste.
- **Phase 1** : PHP construit la liste de commandes pour quelques primitives (rect/texte) avec positions **fixées à la main** (pas de moteur de layout encore) — un round-trip complet PHP → natif → Canvas pour un écran statique simple.
- **Phase 2** : moteur de layout basique (Column/Row à un axe) — Text/Container/Column/Row/Button obtiennent un vrai positionnement automatique.
- **Phase 3** : hit-testing + dispatch d'action — les boutons redeviennent tapables, reliés au système `Screen::handle()` existant.
- **Phase 4** : vraie mesure de texte, primitives supplémentaires (image, icônes en path, dégradés).
- **Phase 5** : animations pilotées par `Choreographer`, interpolation native — plus de bidouille CSS/FLIP.
- **Phase 6** : équivalent iOS (Core Graphics), miroir des phases 1-5.
- **Phase 7** : migration progressive des ~75 widgets existants vers le nouveau chemin de rendu, **derrière un mode explicite** — le chemin WebView actuel continue de fonctionner pendant toute la transition, aucune régression sur ce qui marche déjà.

## Ce qui ne change pas

Tout le reste du framework (état des `Screen`, actions `onXxx()`, base de données, paiements, services device, auth sociale) reste identique — seul le **rendu** change. C'est délibéré : ne pas casser ce qui est déjà vérifié sur device pendant qu'on construit ça en parallèle.

## Critère d'arrêt — "niveau Flutter/React Native" n'en est pas un

Ni Flutter ni React Native n'ont de ligne d'arrivée fixe — les deux représentent des milliers d'années-ingénieur cumulées (moteur de rendu, catalogue de widgets, DevTools, écosystème de plugins). Viser une parité totale comme objectif est un objectif qui recule indéfiniment, pas un jalon. Ce dont ce projet a réellement besoin, c'est d'un seuil concret de "assez bon pour servir un vrai écran de l'app" :

1. **Une bibliothèque de widgets réutilisables**, pas seulement des primitives de layout — un vrai `Button`/`Card`/`ListTile` qu'on compose, comme `Button.php`/`Container.php` côté HTML, plutôt que de reconstruire `Container` + `Center` + `Text` à la main à chaque écran.
2. **Un écran réel de l'app** (pas une recréation de capture de référence) rendu entièrement via ce chemin, mesurable en usage normal.
3. **Un chiffre de performance réel**, pas une intuition — temps de rendu (tap → trame affichée) mesuré sur device, comparé au chemin WebView existant sur un écran équivalent.
4. **Aucune régression** sur le chemin WebView existant pendant toute la transition (déjà garanti par le flag et la séparation de code).

Une fois ces quatre points vrais pour au moins un écran de production, le moteur a atteint son objectif utile — le reste (plus de widgets, plus d'écrans migrés, iOS) devient une question de temps disponible, pas de faisabilité technique restant à prouver.
