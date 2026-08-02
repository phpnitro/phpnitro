# Feuille de route — ce qui manque face à Flutter / React Native

Réécrite après la bascule complète vers le moteur de rendu natif (PHP layout/paint -> commandes JSON -> vrai `android.graphics.Canvas`, zéro WebView/HTML/CSS dans le pipeline de rendu). L'ancienne version de ce document décrivait un framework WebView+Tailwind qui n'existe plus — `packages/ui/src` ne garde que `Color`/`NativeDrawCommand`, tout le reste vit sous `Native/`. Voir [docs/architecture.md](docs/architecture.md) pour le fonctionnement actuel.

---

## Niveau 1 — Bloquant pour rivaliser vraiment avec Flutter

1. **Le modèle d'exécution reste HTTP-par-interaction, pas un arbre en mémoire.** Chaque tap refait tout le cycle layout+paint côté PHP et rejoue les commandes côté Kotlin — rapide (quelques ms de `renderTimeMs`), mais ce n'est pas du diffing d'arbre virtuel ni du hot-reload avec état préservé. `RenderDismissible` (glisser) est la seule interaction qui échappe déjà à ce modèle (état 100% côté client pendant le geste, sync uniquement au relâchement) — le pattern à généraliser pour drag-to-reorder, pinch-to-zoom, etc.
2. **iOS n'existe pas réellement** — pont natif Swift jamais compilé ni testé, et il ciblait l'ancienne architecture WebView. Reste : cross-compiler php-src pour `arm64-apple-ios`, écrire l'équivalent Swift de `NativeCanvasView.kt`/`NativeRenderPocActivity.kt` (un vrai `CALayer`/`UIView` qui rejoue les mêmes commandes JSON), compiler sur un vrai Xcode. Aucun Mac disponible actuellement.
3. **Pas de tests E2E automatisés** — aucun test scripté sur émulateur/device (équivalent `integration_test`/Detox/Maestro), pas de non-régression visuelle. La vérification actuelle est `php -l` + PHPUnit + curl sur `/native/layout-demo` + un vrai `gradle assembleDebug` — solide pour attraper les régressions de compilation/logique, aveugle aux régressions purement visuelles.
4. **Pipeline de publication absent** — signing Android + R8 faits, mais aucune fiche Play Store, politique de confidentialité, captures d'écran. Rien côté iOS.

## Niveau 2 — Important pour l'usage quotidien

5. **Scroll imbriqué indépendant** — `RenderLazyList` virtualise une liste plein écran, mais il n'existe pas encore de scrollable imbriqué dans un autre (ex. un carrousel horizontal DANS une liste verticale, façon `CustomScrollView`/`NestedScrollView`).
6. **Pas de shaders/dessin par frame** — `RenderCustomPaint` dessine une fois au montage, pas de boucle de rendu à 60fps pilotée par l'app elle-même (pas de jeu, pas de visualisation animée en continu). Cohérent avec le modèle HTTP-par-interaction (#1) : une vraie boucle de rendu continue nécessiterait de sortir de ce modèle pour ce cas d'usage précis.
7. **Reorderable list / drag-to-reorder** — pas encore construit, mais `RenderDismissible` (#1) est exactement le bon patron à copier : état de drag 100% Kotlin, sync PHP au relâchement seulement.
8. **Un seul device réel testé** régulièrement — pas de couverture multi-version Android, tablettes, fabricants.
9. **Capacités device non vérifiées en conditions réelles** — NFC (lecture jamais confirmée avec un vrai tag), InAppPurchase (jamais contre un vrai produit Play Console), notifications push (jamais contre un vrai projet Firebase/APNs).

## Niveau 3 — Confort développeur / écosystème

10. **DevTools inexistant pour le pipeline natif** — l'ancien panneau (route/temps/mémoire/état) était injecté dans la page HTML, supprimé avec Tailwind. Pas d'inspecteur d'arbre `RenderNode`, pas de profiler visuel — `renderTimeMs` dans chaque réponse JSON et les logs Logcat (`NativeCanvasView`/`NativeRenderPoc`) sont l'outillage actuel.
11. **Pas de hot-reload avec état préservé — et ce n'est pas qu'un manque à combler, c'est une contrainte de runtime.** `/_dev/version` (watch de fichiers) et `phpx dev:push` déclenchent un re-fetch complet à la prochaine interaction, jamais un patch en place façon Flutter, parce qu'il n'existe — et ne peut pas exister sous cette forme — d'arbre de widgets PHP vivant en mémoire entre deux requêtes (chaque `RenderNode::layout()`/`paint()` repart de zéro, voir #1). Contrairement à la VM Dart, PHP n'a aucun mécanisme natif pour re-définir le code d'une classe dont des instances vivent déjà (pas de "code swap" à la Erlang/Dart) : un vrai hot reload à état profondément préservé nécessiterait soit un process PHP persistant (Swoole/RoadRunner) avec une migration bespoke et fragile de l'état à chaque fichier modifié, soit un changement de paradigme complet — un chantier au gain incertain, pas juste une fonctionnalité oubliée. Ce qui survit déjà, sans effort : `$_SESSION` (fichier, survit même à un kill du process app), `screenStack` côté Kotlin, les valeurs de champs de formulaire côté client, l'état de geste (dismiss/reorder/tabs, 100% client). L'amélioration réaliste à court terme est côté confort : faire de `phpx dev:push --watch` un comportement automatique de `phpx serve` plutôt qu'une commande à lancer dans un second terminal.
12. **Zéro écosystème tiers** — packages internes au monorepo uniquement, rien publié sur Packagist.
13. **Documentation à jour mais pas générée** — `docs/architecture.md`/`widgets.md`/`device-and-native.md` réécrits pour refléter le moteur natif, mais à la main (l'ancien `phpx docs:api` générait depuis les docblocks d'un framework qui n'existe plus — à réévaluer si un générateur redevient utile pour `Native/`).
14. **Paiements/auth sociale/maps/dialogs supprimés, pas reconstruits en natif** — `packages/payments`, `packages/socialauth`, `packages/launcher`, `packages/diagnostics` (CrashReporter) étaient du code WebView-JS-bridge mort (zéro appelant après la bascule native) et ont été supprimés plutôt que réécrits. `packages/maps`/`packages/dialogs` ont un vrai remplaçant natif complet (`NativeMapView`/osmdroid, `NativeAlertButton`/`NativeConfirmButton`) ; les trois autres sont un vrai chantier futur (SDK natif par gateway/provider), pas une résurrection de l'ancien code.

---

## Ce qui EST fait, pour référence (ne pas re-vérifier)

Moteur de layout à contraintes complet (Flutter's `BoxConstraints`), ~50 widgets natifs (`packages/ui/src/Native/`), texte riche multi-styles avec wrap réel (`RenderRichText`), système d'animation implicite unifié (`RenderHero`/`RenderAnimated`, interpolation couleur+géométrie par commande), liste virtualisée avec fenêtre de préchargement (`RenderLazyList`), un geste continu réel (`RenderDismissible`), impression PDF native (`PrintManager`), ~30 capacités device natives (`NativeDeviceBridge.kt`), traduction sur l'appareil (ML Kit), cartes OpenStreetMap réelles (osmdroid). Voir [docs/widgets.md](docs/widgets.md) et [docs/device-and-native.md](docs/device-and-native.md) pour le détail.

**Accessibilité (arbre de nœuds virtuel `CanvasAccessibilityNodeProvider` dans `NativeCanvasView.kt`) — confirmée fonctionnelle sur device réel le 2026-07-31** (Infinix X6532, `adb shell uiautomator dump`) : 14 nœuds cliquables exposés (les 13 `hitRegions` de l'écran d'accueil + le bouton dev-tools), avec des `content-desc` cohérents ("Incrémenter", "Réglages Préférences réelles", "Documents Étape 3/4 — checklist", "Accueil"/"Widgets"/"Device"/"Backend"...). Le premier test (même jour) n'exposait qu'1 nœud sur 13 et la classe `NativeCanvasView` n'apparaissait même pas dans le dump — cause identifiée : le nœud racine (`HOST_VIEW_ID`) omettait `packageName`/`isVisibleToUser`/`isEnabled`, que chaque nœud virtuel enfant définissait pourtant correctement ; un nœud racine incomplet suffit à faire rejeter tout le sous-arbre virtuel par les services d'accessibilité. Corrigé en ajoutant ces trois champs au nœud racine. Reste non vérifié : le rendu vocal réel via un lecteur d'écran (Google TalkBack n'est pas installé sur cet appareil Transsion — seul `uiautomator`, qui interroge la même API, a été utilisé).

## Priorité si l'objectif est une v1 production (pas la parité totale)

1. Généraliser le patron `RenderDismissible` à drag-to-reorder (#7) — c'est le geste manquant le plus demandé dans une vraie app à listes.
2. Tests E2E même basiques sur émulateur (#3) — la confiance actuelle repose sur curl + lecture de JSON, pas sur ce que l'utilisateur voit réellement.
3. iOS (#2) — le chantier le plus lourd, nécessite un Mac, à traiter en dernier.
